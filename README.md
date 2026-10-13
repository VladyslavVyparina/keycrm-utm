# KeyCRM UTM capture

Browser scripts that remember campaign parameters from the landing URL and send them with the lead into KeyCRM, including an OpenCart checkout.

## Problem

Ads were sending people to the site with `utm_source` and the rest of the usual parameters, and the KeyCRM lead arrived with a name and a phone and no idea which campaign produced it.

## What was built

In `site/`:

- `utm-capture.js` stores the parameters when the visitor lands.
- `keycrm-form.js` attaches them to the form that creates the lead.
- `opencart-checkout-utm.js` does the same on OpenCart checkout.
- `example-form.html` and `utm-test.html` are local pages for checking the capture before it is pasted into the theme.
- `config.example.js` shows the shape of the config. Put real keys only in a local file that is not committed.

`opencart/` and `vps/` hold the theme-side notes and the server-side placement. Call transcripts that were used while wiring this were left out of the public tree.

## How it works

On the first page view the capture script reads the query string and keeps the UTM set for the session. The form script reads that set and adds it to the payload KeyCRM already accepts on the lead. Checkout uses the same stored values so a purchase and a form lead are attributed the same way. If the visitor arrives with no parameters, nothing is invented.

## Stack

JavaScript, OpenCart, KeyCRM.

## Run locally

Open `site/utm-test.html` in a browser with a query string such as `?utm_source=ads&utm_campaign=spring` and confirm the form payload contains those fields before installing the scripts on the shop.

## What this public copy leaves out

Meeting transcripts and any live shop credentials.
