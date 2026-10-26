# Privatus Analytics for Joomla

**Real visitor numbers for your Joomla site. No cookies, no cookie pop-up, no bots.**

You want to know if people visit your Joomla site. But Google Analytics uses
cookies, so in many countries you must show a cookie pop-up first (the box that
asks visitors to say yes to cookies). Many people click no, so they never show
up in your numbers. Bots add fake visits on top.

This system plugin adds the Privatus Analytics script to your Joomla site. The
script sets no cookies and keeps no IP addresses. Bots are filtered out before
they are counted.

So you see how many real people came, which pages they looked at and where they
came from, on one simple dashboard. A report comes to your inbox each week, and
you can set alerts for when traffic jumps or drops. And you don't need a cookie
pop-up for your analytics.

**Free plan, no card:** 10,000 events a month (an event is a page view or an action you track) on up to 3 sites. [Start free at privatusanalytics.com](https://privatusanalytics.com).

- **No cookies.** No cookies and no hidden IDs on your visitors' devices, and no IP
  addresses are kept. Laws differ by country, so check your own legal advice.
- **Real people, not bots.** Known bots, data centers and spam visits are left
  out on every plan.
- **Small and fast.** The script is about 3 KB when compressed.

## What this package does

- Adds the Privatus Analytics tracking script to your Joomla site with a system
  plugin.
- Only prints the browser script. It never sends events from your server and
  sets no cookies.
- Until you enter a site id, the plugin does nothing.

You need a Privatus Analytics account to see reports, and there is a free plan.
Sign up at https://privatusanalytics.com.

## Requirements

* Joomla 4.4, 5.x or 6.x (tested on 4.4.14, 5.4.6 and 6.1.0).
* PHP 8.1 or later.
* A Privatus Analytics site id (it starts with `pa_`). You find it under Site
  settings, Tracking.

## Install

1. Download `plg_system_privatusanalytics-1.0.0.zip` from the
   [releases page](https://github.com/Privatus-Analytics/joomla/releases).
2. In the administrator, go to System, Install, Extensions and upload the zip.
3. Go to System, Manage, Plugins, find **System - Privatus Analytics** and
   open it.
4. Paste your site id, set the plugin to **Enabled** and save.

Reload your site, view the page source and look for `/js/pa.js` in the head.
The [Privatus Analytics docs](https://privatusanalytics.com/docs) cover the
rest of the setup.

## Options

| Option | What it does |
|---|---|
| Site ID | The `data-site` attribute. Required |
| Script host | Where `/js/pa.js` is served. Defaults to `https://privatusstats.com`. Change it only if you host your own copy |
| Modules | Time on page and scroll depth, automatic events, Web Vitals, click maps and on-site surveys. Tick none to use the tracker default |
| Excluded paths | The `data-exclude` attribute. Comma or line separated path globs the tracker never counts, such as `/checkout/*` |
| Allowed domains | The `data-domains` attribute. Count only these hostnames |
| Single-page app mode | The `data-spa` attribute |
| Manual pageviews | The `data-manual` attribute |
| Skip logged-in users | No script for anyone signed in to the site |
| Skip these user groups | No script for signed-in users in the chosen groups (and the groups under them) |

Each tracker attribute is explained in the [script attributes
reference](https://privatusanalytics.com/docs/tracker/attributes).

## Good to know

* The script is printed on the site frontend only, never in the administrator.
* If you use a page cache, the skip options for signed-in users only work when signed-in users are not served cached pages. Joomla's **System - Page Cache** plugin does not cache pages for signed-in users.
* Caching and optimization extensions can combine or delay scripts. Leave
  `pa.js` out of any combine or delay feature.
* The plugin makes no requests of its own. The update check Joomla runs is the
  standard one for every extension and only fetches a small version file.

## Build the zip yourself

```sh
./scripts/build.sh
```

The zip lands in `dist/`. Run the plain PHP tests and a real install check
(Docker is required) with:

```sh
docker run --rm -v "$PWD":/app -w /app joomla:5.4.6-php8.3-apache php tests/TagBuilderTest.php
./scripts/verify-docker.sh                                   # Joomla 5.4.6
./scripts/verify-docker.sh joomla:4.4.14-php8.2-apache       # Joomla 4.4
```

`verify-docker.sh` starts a throwaway Joomla and database, installs the zip,
saves the options through the administrator form and checks that the tag is on
the frontend and not in the administrator.

## License

GNU General Public License version 2 or later. See [LICENSE.txt](LICENSE.txt).
Privatus Analytics is a product of Two Phase LLC, Wyoming, USA.
