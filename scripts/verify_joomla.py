"""Drives a local Joomla over HTTP: enable the plugin, save its params, check the tag.

Used by verify-docker.sh against a throwaway container. Standard library only.
"""
import html.parser
import http.cookiejar
import json
import os
import re
import subprocess
import sys
import urllib.parse
import urllib.request

BASE = os.environ["BASE"]
SITE_ID = "pa_VERIFY12345"


class Forms(html.parser.HTMLParser):
    """Collects every named field of every form in a page."""

    def __init__(self):
        super().__init__()
        self.forms = []
        self._select = None
        self._textarea = None

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == "form":
            self.forms.append({"id": a.get("id"), "action": a.get("action"), "fields": []})
        elif not self.forms:
            return
        elif tag == "input" and a.get("name"):
            kind = (a.get("type") or "text").lower()
            if kind in ("checkbox", "radio") and "checked" not in a:
                return
            if kind in ("submit", "button", "file"):
                return
            self.forms[-1]["fields"].append([a["name"], a.get("value", "")])
        elif tag == "select" and a.get("name"):
            self._select = a["name"]
        elif tag == "option" and self._select and "selected" in a:
            self.forms[-1]["fields"].append([self._select, a.get("value", "")])
        elif tag == "textarea" and a.get("name"):
            self._textarea = a["name"]
            self.forms[-1]["fields"].append([a["name"], ""])

    def handle_endtag(self, tag):
        if tag == "select":
            self._select = None
        if tag == "textarea":
            self._textarea = None

    def handle_data(self, data):
        if self._textarea and self.forms:
            self.forms[-1]["fields"][-1][1] += data


jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))


def get(path):
    with opener.open(urllib.request.Request(BASE + path, headers={"User-Agent": "verify"})) as r:
        return r.read().decode("utf-8", "replace"), r.geturl()


def post(path, fields):
    data = urllib.parse.urlencode([tuple(x) for x in fields]).encode()
    with opener.open(urllib.request.Request(BASE + path, data=data, headers={"User-Agent": "verify"})) as r:
        return r.read().decode("utf-8", "replace"), r.geturl()


def form(page, form_id):
    p = Forms()
    p.feed(page)
    return next(f for f in p.forms if f["id"] == form_id)


def sql(query):
    out = subprocess.run(
        ["docker", "exec", os.environ["DB_CONTAINER"], "mariadb", "-uroot", "-p" + os.environ["DB_PASS"],
         "joomla", "-N", "-r", "-e", query], capture_output=True, text=True, check=True)
    return out.stdout.strip()


failures = 0


def check(name, ok, detail=""):
    global failures
    print(("ok   " if ok else "FAIL ") + name + (("  " + detail) if detail and not ok else ""))
    if not ok:
        failures += 1


table = sql("select table_name from information_schema.tables where table_schema='joomla' and table_name regexp '^[^_]+_extensions$'")
prefix = table[: -len("extensions")]

# 1. Log in to the administrator.
page, _ = get("/administrator/index.php")
login = form(page, "form-login")
fields = [f for f in login["fields"] if f[0] not in ("username", "passwd")]
fields += [["username", os.environ["ADMIN_USER"]], ["passwd", os.environ["ADMIN_PASS"]]]
page, url = post("/administrator/index.php", fields)
check("admin login", "com_cpanel" in page or "Control Panel" in page or "Home Dashboard" in page)

# 2. Without a site id, nothing is printed on the frontend.
sql(f"update {prefix}extensions set enabled=1 where element='privatusanalytics' and folder='system'")
front, _ = get("/index.php")
check("no tag before a site id is saved", "/js/pa.js" not in front)

# 3. Open the plugin form and save params.
ext_id = sql(f"select extension_id from {prefix}extensions where element='privatusanalytics' and folder='system'")
page, _ = get(f"/administrator/index.php?option=com_plugins&task=plugin.edit&extension_id={ext_id}")
f = form(page, "style-form")
check("language strings load", "Site ID" in page and "PLG_SYSTEM_PRIVATUSANALYTICS_SITE_ID_LABEL" not in page)
check("plugin form lists the params", any(n == "jform[params][site_id]" for n, _ in f["fields"]))
overrides = {
    "jform[params][site_id]": SITE_ID,
    "jform[params][exclude]": "/checkout/*, /thank-you",
    "jform[params][spa]": "history",
    "jform[params][skip_logged_in]": "0",
    "task": "plugin.apply",
}
fields = [x for x in f["fields"] if x[0] not in overrides and not x[0].startswith("jform[params][modules]")]
fields += [[k, v] for k, v in overrides.items()]
fields += [["jform[params][modules][]", m] for m in ("engage", "auto", "vitals")]
page, _ = post("/administrator/index.php?option=com_plugins&view=plugin&layout=edit&extension_id=" + ext_id, fields)
params = sql(f"select params from {prefix}extensions where extension_id={ext_id}")
saved = json.loads(params)
check("params saved", saved["site_id"] == SITE_ID and saved["exclude"] == "/checkout/*, /thank-you"
      and saved["spa"] == "history", params)
check("modules saved", saved["modules"] == ["engage", "auto", "vitals"], params)

# 4. Frontend has the tag, administrator does not.
front, _ = get("/index.php")
tag = re.search(r"<script[^>]*/js/pa\.js[^>]*>", front)
print("frontend tag:", tag.group(0) if tag else None)
check("frontend has the tracker", tag is not None)
if tag:
    t = tag.group(0)
    check("src is the default host", 'src="https://privatusstats.com/js/pa.js"' in t, t)
    check("defer", " defer" in t, t)
    check("data-site", f'data-site="{SITE_ID}"' in t, t)
    check("data-modules", 'data-modules="engage,auto,vitals"' in t, t)
    check("data-exclude", 'data-exclude="/checkout/*,/thank-you"' in t, t)
    check("data-spa", 'data-spa="history"' in t, t)
check("tag appears once", front.count("/js/pa.js") == 1)
admin, _ = get("/administrator/index.php")
check("administrator page has no tag", "/js/pa.js" not in admin)
admin2, _ = get("/administrator/index.php?option=com_plugins&view=plugins")
check("administrator plugin list has no tag", "pa.js" not in admin2)

# 5. Skip logged-in users: the admin is logged in on the frontend only after a site login,
#    so test the user-group skip by logging in on the site.
page, _ = get("/index.php?option=com_users&view=login")
sf = form(page, "com-users-login__form")
fields = [x for x in sf["fields"] if x[0] not in ("username", "password")]
fields += [["username", os.environ["ADMIN_USER"]], ["password", os.environ["ADMIN_PASS"]]]
post("/index.php?option=com_users&task=user.login", fields)
front, _ = get("/index.php")
check("tag still shown to a logged-in user when the skip is off", "/js/pa.js" in front)

page, _ = get(f"/administrator/index.php?option=com_plugins&task=plugin.edit&extension_id={ext_id}")
f = form(page, "style-form")
fields = [x for x in f["fields"] if x[0] not in ("jform[params][skip_logged_in]", "task")]
fields += [["jform[params][skip_logged_in]", "1"], ["task", "plugin.save"]]
post("/administrator/index.php?option=com_plugins&view=plugin&layout=edit&extension_id=" + ext_id, fields)
front, _ = get("/index.php")
check("tag hidden from a logged-in user when the skip is on", "/js/pa.js" not in front)

# 6. Skip by group: turn the skip off, pick Super Users (group 8).
page, _ = get(f"/administrator/index.php?option=com_plugins&task=plugin.edit&extension_id={ext_id}")
f = form(page, "style-form")
fields = [x for x in f["fields"] if x[0] not in ("jform[params][skip_logged_in]", "task")
          and not x[0].startswith("jform[params][skip_groups]")]
fields += [["jform[params][skip_logged_in]", "0"], ["jform[params][skip_groups][]", "8"], ["task", "plugin.save"]]
post("/administrator/index.php?option=com_plugins&view=plugin&layout=edit&extension_id=" + ext_id, fields)
front, _ = get("/index.php")
check("tag hidden from a skipped user group", "/js/pa.js" not in front)
anon = urllib.request.build_opener()
with anon.open(urllib.request.Request(BASE + "/index.php", headers={"User-Agent": "verify"})) as r:
    anon_page = r.read().decode()
check("tag still shown to guests", "/js/pa.js" in anon_page)

print("\nFAILURES: %d" % failures)
sys.exit(1 if failures else 0)
