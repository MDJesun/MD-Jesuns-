# MD Jesun website: static version for Vercel

This folder is your complete website as plain HTML, CSS and JavaScript, ready for Vercel.
It looks and behaves exactly like the PHP version: same design, animations, 3D carousel, video pop-ups and contact form.

## What's inside

| File / folder | What it is |
|---|---|
| `index.html`, `portfolio.html`, `about.html`, `services.html`, `contact.html` | Your pages. On Vercel they open as `/`, `/portfolio`, `/about`, `/services`, `/contact`. |
| `404.html` | The "page not found" screen. |
| `assets/` | Styles, the site script and built-in images. |
| `uploads/` | Your uploaded images (only the ones the pages use). |
| `api/contact.js` | The contact form. It emails each enquiry to you through Gmail. |
| `vercel.json` | Settings: clean addresses, redirects from the old `.php` addresses, caching, security headers. |

## Put it online (about 5 minutes)

**Option A: with GitHub (no coding)**
1. Create a free account on github.com, click **New repository**, name it (for example `md-jesun-website`) and create it.
2. On the new repository page click **uploading an existing file**, drag **everything inside this folder** in (not the folder itself), and click **Commit changes**.
3. Go to vercel.com, sign in with GitHub, click **Add New… → Project**, pick the repository and click **Deploy**. You don't need to change any settings.

**Option B: with the Vercel command line**
In Terminal, inside this folder: `npx vercel` (first time), then `npx vercel --prod`.

## Make the contact form email you (2 minutes)

The form sends enquiries to your Gmail. Vercel needs a Google App Password for that:
1. Turn on 2-Step Verification in your Google Account, then open myaccount.google.com/apppasswords, create one named "Website" and copy the 16 letters.
2. In Vercel open your project → **Settings → Environment Variables** and add:
   - `GMAIL_USER`: your Gmail address
   - `GMAIL_APP_PASSWORD`: the 16-letter App Password
   - `CONTACT_TO` (optional): where enquiries should go, if not the same Gmail
3. Go to **Deployments**, click **⋯** on the newest one and choose **Redeploy** (new settings only apply after a redeploy).
4. Send yourself a test message from the Contact page.

Until this is set up, the form tells visitors to email you directly, so no enquiry silently disappears.
If an email fails, the reason is in Vercel → your project → **Logs**.

## Changing content later

A static site has no Admin Panel. To change texts, projects or images:
- **Easiest:** keep editing in the Admin Panel of the PHP version on your Mac (MAMP), then export again and upload the new files.
- Or edit the `.html` files directly. Text is plain and easy to find.

## Notes
- The Testimonials page is switched off in your Admin Panel, so it isn't included. Its old address goes to the home page.
- The share image for social media (`og:image`) needs your final web address. Add it once your domain is known.
- Anti-spam: a hidden trap box and a 2-second check. Robots get a fake "sent" message and nothing is emailed.
