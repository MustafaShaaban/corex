# The Arabic review (spec 121, US-NFR-02, TC-NFR-02-07)

The Arabic catalogue (`plugins/corex-core/languages/corex-ar.po`) was drafted in this release from the translation
template, following `plugins/corex-core/languages/glossary-ar.md`. Decision NFR-D3 asks that a native Arabic speaker
the owner names reviews it before R2.1 ships. This file is where that review is recorded.

**Status: owed.** No reviewer is named yet.

## What the reviewer reads

1. The screenshots of the Arabic proof: the `quality-findings` artifact of the pull request's or the release
   candidate's **Quality checks** job, folder `arabic/`. Every CoreX admin screen and tab, every theme template with
   the Company kit and the Contact form with its errors, each at 375 and 1280px, light and dark.
2. The catalogue itself, for strings the screenshots do not show (emails, rare errors). Search it by English text.

To see one screen live on a scratch site: copy `tests/e2e/fixtures/corex-e2e-arabic.php` into
`wp-content/mu-plugins/` and set the cookie `corex_e2e_locale=ar` in the browser.

## Strings drafted after the first catalogue

**Added 2026-11-11.** Merging `main` into this branch (specs 117, 118, 119, 120, 122, 129 and the fixes since
v0.48.1) brought 825 CoreX strings the first draft did not have. They were drafted the same way, by machine,
following the glossary, and checked for placeholders, markup and plural forms; none has been read by a native
speaker. In `corex-ar.po` each carries the comment `# Drafted 2026-11-11 (spec 121, merge with main)`, so the
reviewer can find every one of them by that line. The reviewer reads all of them, not a sample: most are on
screens the proof's screenshots show (the assistant, Access & Abilities, Settings, Forms & Flows, Email
Studio), and the email ones only in the catalogue and the email previews.

**Added 2026-11-16 and 2026-11-20.** Later batches, drafted the same way and marked in `corex-ar.po` by their own
comment line: `# Drafted 2026-11-16 (spec 134 slice 2)` (the three section band names) and
`# Drafted 2026-11-20 (spec 124 slice 7)` (the flow notices: the test-site notice, the paused panel, its back-by
line). The slice 7 hand-over drew its Arabic with the tanween after the alef (مؤقتاً); the catalogue writes it before
(مؤقتًا), as everywhere else: the reviewer settles the spelling once for the whole catalogue.

<details>
<summary>The 825 originals, by the plug-in or add-on that asks for them</summary>

**addons/corex-assistant** (118)

- CoreX Assistant
- Assistant
- The chat on every public page: it answers from your site’s own content, through the services below in your order, and hands visitors to your team when it cannot help.
- Save changes
- This change could not be saved: the page was open too long, or you may not change these settings. Open the screen again and save from there.
- Suggested question %1$d (%2$s)
- Words in %s (blank: the built-in ones)
- Suggested questions
- A chat button on every public page. It shows once the switch is on and at least one service below is on with a key.
- Show the assistant
- On, set in the site’s .env file
- Off, set in the site’s .env file
- Button image
- Choose image
- About 32 pixels on the button; a square image with a transparent background works best. Blank: a speech-bubble icon.
- Asked from the top: when one is out of its free limit, the next answers. Paid services cost money for each answer; keep them last and on only if you want them. Visitors never see which service answered.
- Order
- Service
- Paid
- Free tier
- Set in the site’s .env file
- Saved (type to replace)
- Paste the key
- %s API key
- No key
- Last failed %1$s: %2$s
- Resting until %s.
- Ready.
- In %1$s: %2$s
- Booking
- When the team takes calls. With this on, the callback form lets a visitor pick a time, shown in their own time zone; the team gets it in both. It is a time the team confirms, not a reservation.
- Let visitors pick a time
- Time zone
- Working days
- Opens
- Closes
- A closing time at or before the opening time closes the next day.
- Slot length
- Notice (minutes)
- Days shown
- Today, as visitors elsewhere see these hours:
- Questions a visitor may ask in an hour
- Questions a visitor may ask in a day
- Questions the whole site answers in a day
- Questions sent to paid services in a day
- Limits
- Past a limit the chat offers its callback form instead of answering, until the next hour or day (UTC). Today: %1$d questions, %2$d sent to paid services.
- Check services
- Asks each service with a key, on or off, a visitor’s question with the full instructions, and shows which answered and how fast. A paid service is charged for its question and it counts against today’s paid limit. Save new keys first.
- %s on
- Google Gemini
- Groq
- OpenRouter
- Mistral
- OpenAI
- Anthropic Claude
- UTC
- Sunday
- Monday
- Tuesday
- Wednesday
- Thursday
- Friday
- Saturday
- Assistant callback request
- Full name
- Reach me by
- Phone call
- Requested time
- Requested time (UTC)
- Visitor time zone
- Chat (from the visitor’s browser)
- Sent from
- %s Assistant
- Answers from our website · Online
- Chat with %s
- Have a question?
- Ask %s. It replies right away.
- Hello! I’m %s. I can tell you about what we do and how to get started. How can I help?
- What do you offer?
- How do I get started?
- How can I contact your team?
- Type your question…
- An AI assistant that answers from our website only, and can make mistakes. Please don’t share sensitive information here.
- I’d rather talk to someone on the team
- New chat
- %s is writing
- I can’t answer that right now. Leave your details and our team will get back to you.
- This page has been open for a while. Refresh it to keep chatting.
- Shall we contact you?
- Leave your details and someone from our team will get back to you soon.
- Your name
- Mobile number
- Country code
- (optional)
- Call
- Best time to reach you (optional)
- When should we reach you?
- Any time that suits the team
- Times are in your time zone (%s). The team confirms the time with you.
- Attach a summary of this chat, so the team knows what you need
- Send request
- Sending…
- Please check the highlighted fields.
- Add your name.
- Add a number we can reach you on.
- Add your email to be contacted by email.
- Thank you, %s. We have your request, and our team will contact you soon.
- %1$s (%2$s): answered in %3$s s
- %1$s (%2$s): did not answer after %3$s s, %4$s
- %s (switched off)
- Checking…
- No service has a key yet.
- The check could not run. Reload the page and try again.
- Choose the button image
- Use this image
- CoreX Assistant (context: block title)
- The assistant's button and chat (spec 129). Printed on every public page by the footer when the assistant is on; not for placing in content. (context: block description)

**addons/corex-captcha** (4)

- a key is missing
- the provider refused the secret key
- the provider could not be reached
- this driver needs no keys

**addons/corex-email** (93)

- You receive this email because you wrote to %s.
- You receive this email because this address receives notifications from %s.
- You receive this email because this address was entered on %s.
- You receive this email because you subscribed to updates from %s.
- You receive this email because of an account on %s.
- Only an email template CoreX ships can be reset.
- The layout this template is arranged by is not available.
- Contact confirmation
- Thanks, we have your message
- Thanks, we have your message.
- Thanks for reaching out. A member of the team will reply soon. Here is a copy of what you sent:
- Warm regards,
- The %s team
- Admin notification
- New submission: %s
- Someone filled in %1$s on %2$s. These are the answers:
- Double opt-in
- Confirm your subscription
- One last step
- You are nearly subscribed to updates from %s. To protect your inbox, please confirm that this address belongs to you.
- Button not working? Paste this link into your browser:
- If you did not sign up, you can ignore this email: no subscription is made.
- Newsletter welcome
- Welcome to %s
- You are subscribed
- Welcome to %s.
- Thanks for confirming. You will now get our updates by email.
- Visit %s
- Three sample answers
- Could we book a call next week to talk about our new site?
- Contact form
- The mail server did not accept the email.
- 1 day
- Manage forms
- approved
- Granted for the launch week.
- Sample %s
- This template cannot be drawn yet.
- Enter a valid email address: %s is not one.
- an empty field
- [Test] %s
- Every answer, with its label, in the form's order
- The layout revision this template is arranged by is not available. Choose another.
- Captured: nothing left this site
- Delivered to the mail server
- Failed: the mail server did not accept it
- Blocked: no mail provider is set
- Blocked: live delivery is switched off
- Blocked: this site does not say which kind of site it is
- The subject cannot contain a line break.
- The From name cannot contain a line break.
- The From address is not a valid email address: %s
- Unknown variable: %s. / Unknown variables: %s.
- %1$s is not available in %2$s.
- The partial "%s" is not available.
- a form email
- a newsletter email
- an account email
- an access request email
- this email
- Site address
- Privacy page
- Name the visitor gave
- Email the visitor gave
- Message the visitor wrote
- Form title
- All answers
- Link to the submission
- Why the email failed
- Action link (confirm, reset)
- Unsubscribe link
- Preferences link
- Account name
- Account email
- Account page
- How long the reset link works
- When the reset was asked
- Access request number
- Ability asked for
- Area asked for
- Decision
- Reviewer note
- Requester name
- Requester email
- Reviewer name
- Form field "%s"
- Every email
- Form emails
- Newsletter emails
- Account emails
- Access request emails
- Not supplied by CoreX: your site's code fills these
- A new "%s" form submission was received:

**addons/corex-guides** (15)

- Choose a page under Settings in the CoreX menu: General and appearance, Email, Captcha, Media, Insights, Guides, Advanced.
- Each page saves on its own. The bar at the bottom says "Unsaved changes" once you edit something; Discard puts the page back as it was, and leaving the page with unsaved changes asks first.
- A CoreX Settings page, with the pages listed under Settings and the save bar at the bottom.
- Secrets are never shown back to you; the field says whether a value is saved. Saving it blank keeps what is stored. To take a saved key off the site, use Remove beside it and save.
- Remove asks first and says what stops working. The key is deleted when you save the page, and the removal is kept in the activity log without the key.
- A field you cannot edit says where its value is set.
- "Set in the site's .env file" means the value comes from that file on the server, and wins over anything typed here; "Locked by the network administrator" means a multisite network decides it. Change it there.
- Terms page — the page with your terms and conditions.
- The launch checklist on the Overview checks that this page is published. Leave it on "None chosen" until the page exists.
- Login logo link — where the logo on the login screen takes people.
- Leave it blank for this site's home page, or give a web address such as your company's main site. It is the logo's link, not the address people sign in at.
- These settings belong to the Corex Media add-on. While it is off they are shown read-only, with a link to it on the Add-ons screen.
- Convert existing library — make WebP copies of images uploaded before conversion was on.
- It runs in the background and shows how many images were converted, skipped and failed, even if you leave and come back. Originals are kept, and images that already have a WebP copy are skipped. You can cancel it.
- Free from Google. Without it, performance checks share Google's keyless quota and are refused when it runs out. Test connection asks Google whether it accepts the key, and the page remembers the answer.

**addons/corex-kit-company** (12)

- The plan was applied in part: some pages could not be written, so the kit is not marked applied. Check the pages, then apply again.
- The site is now in Production.
- The site was already in Production.
- Going live needs the confirmation phrase. Type it and try again.
- Going live was refused: checks still block it. Resolve them, or confirm the override.
- Reading the launch checklist…
- The launch checklist could not be read.
- The site is live.
- To go live, type the confirmation phrase
- Go live
- Setup is done. Open the launch checklist to see what is left before going live.
- Setup is done and the site is live.

**addons/corex-media** (19)

- A conversion is already running.
- The conversion could not be scheduled on this site.
- No conversion is running.
- The request did not get an answer. Try again.
- Images that could not be converted
- Convert existing library
- Writes a WebP copy of each JPEG and PNG already in the Media library. Originals are kept, and images that already have a WebP copy are skipped.
- Not run yet.
- Media library conversion to WebP
- This server cannot write WebP images, so the library cannot be converted.
- Conversion is off. Turn on "Convert uploads to WebP" and save, then convert the library.
- %1$s converted, %2$s skipped, %3$s failed, of %4$s images.
- Finished on %1$s: %2$s
- Cancelled on %1$s: %2$s
- Stopped on %1$s: %2$s %3$s
- Converting: %s
- the file is missing
- the file cannot be read
- the image could not be converted to WebP

**addons/corex-newsletter** (2)

- Newsletter: welcome
- Manage preferences

**addons/corex-profile** (12)

- Account: password reset
- Account: welcome
- Password reset
- Reset your password for %s
- Reset your password
- On %1$s we were asked to reset the password of the account %2$s. Choose a new one with the button below. The link works for %3$s.
- Reset password
- Did not ask for this? Ignore this email and your password stays the same.
- Welcome to your account
- Welcome, %s.
- Your account on %s is ready. Sign in with the password you chose.
- Go to your account

**plugins/corex-config** (441)

- Role changed
- Role change refused
- Access requested
- Request approved
- Request denied
- Request cancelled
- Request expired
- Decision refused
- Grant revoked
- Grant expired
- Refused
- %1$s, not changed. %2$s
- Asked for %s
- Granted %1$s %2$s
- Refused %1$s %2$s
- %1$s’s request for %2$s was cancelled: an administrator grants it through the role matrix
- %1$s’s request for %2$s ran out before it was decided
- %1$s’s request for %2$s, not decided. %3$s
- Took back %2$s from %1$s
- %1$s’s %2$s ended on its expiry date
- Refused records of %s on the Data screen
- Refused an action on %s
- Refused %s
- the %s area
- %1$s set to %2$s
- Only a site administrator can decide their own access request.
- Who can use each CoreX screen: the CoreX abilities each role holds, the grants made to people, and every access event.
- %s person is waiting for access / %s people are waiting for access
- %1$s: Deny of “%2$s”
- Stored but not applied
- These were set in the role matrix before every CoreX screen checked its ability. Site administrators always hold every CoreX ability, so they are kept and ignored: nobody who administers the site loses anything.
- %s entry / %s entries
- Nothing matches these filters.
- Nothing has been recorded yet. CoreX lists here: role changes and refused ones, access requests and their approval, refusal, cancellation or expiry, grants revoked or ended, and refused visits to CoreX screens and actions.
- Role changes, requests and grants are kept for %1$s days; refusals for %2$s days.
- Every kind
- Everyone
- Person
- Filter
- Audit log pages
- Newer
- Older
- A signed-in person without the CoreX ability a screen needs who opens it is refused with HTTP 403, sees this page with a way to ask for the ability, and the attempt is counted in the audit log.
- CoreX detected %s. CoreX manages only its own abilities here; WordPress capabilities stay with that plugin.
- WordPress
- Added by a plugin or the site
- / %s CoreX ability / / %s CoreX abilities
- Locked
- CoreX abilities
- %s ability, by area / %s abilities, by area
- What the cells say
- Allowed (set here)
- Denied (set here)
- Allowed through site administration (inherited)
- Not granted (inherited)
- Site administrators keep every CoreX ability.
- Anyone who can administer the site holds every CoreX ability, and the matrix cannot deny a role that does. A change that would leave no administrator with full access, or take a critical ability from you, is refused.
- You hold no CoreX ability.
- Site administration
- Your role, %s
- Granted to you until %s
- Granted to you
- %1$s or %2$s
- %1$s (read-only with %2$s)
- Switching to Production or Maintenance
- %1$s and %2$s
- Anyone signed in; each guide follows its screen
- Each CoreX screen opens for the people who hold its ability: through their role in the matrix, a grant approved for them, or site administration.
- Only a site administrator can decide their own access request. Another person who manages access, or a site administrator, decides it.
- “%s” cannot be changed: it comes only with site administration.
- “%s” cannot be denied here: you would lose it yourself.
- “%s” cannot be denied here: no administrator with full access would keep it.
- “%s” cannot be denied to this role: site administrators always hold every CoreX ability.
- “%s” cannot be changed here: it applies to you, and only a site administrator can change their own access.
- “%s” is a Critical ability: only a site administrator can allow it or set it to inherit.
- “%s” cannot be changed.
- %1$s moved %2$s submission to the trash / %1$s moved %2$s submissions to the trash
- %1$s restored %2$s submission from the trash / %1$s restored %2$s submissions from the trash
- %1$s deleted %2$s submission for good / %1$s deleted %2$s submissions for good
- %1$s exported %2$s submission / %1$s exported %2$s submissions
- %s deleted an export
- %s cancelled an access request
- %s imported records
- %s exported records
- Something changed in %s
- %1$s was refused the records of %2$s on the Data screen
- A deleted user
- The command line
- A scheduled task
- %1$s tried to turn on %2$s, and it did not turn on
- %1$s turned on %2$s
- %1$s tried to turn off %2$s, and it did not turn off
- %1$s turned off %2$s
- %1$s added %2$s record / %1$s added %2$s records
- %1$s deleted %2$s record / %1$s deleted %2$s records
- %1$s changed %2$s record / %1$s changed %2$s records
- Theme
- Open Settings > Captcha
- Add a Cloudflare API token and account ID to include the URL-scan security signal in AI and agent readiness runs.
- Included in AI and agent readiness runs
- Not probed yet — run AI and agent readiness
- Probed by the AI and agent readiness check
- AI and agent readiness
- Could not load
- The submission and flow counts could not be read. Reload the page to try again.
- Needs attention
- %s job did not finish / %s jobs did not finish
- %s export finished / %s exports finished
- An email template could not be drawn
- The Email Studio template %1$s could not be drawn, so the site's own email was sent in its place. %2$s
- Not a live site. Email is kept in Email Studio instead of being sent.
- Pre-production. Email is kept in Email Studio unless a mail provider and live delivery are set.
- Switching to this mode also needs “%s”.
- This could not be checked.
- No data yet. Form submissions, newsletter subscribers and job applications appear here once your site receives them.
- Open Data
- CoreX's own records, such as the activity log, are on the Data screen.
- Can edit
- Forms are off.
- %s form / %s forms
- Submission numbers are shown to people who may open the Submissions inbox.
- No submissions yet.
- Open the inbox
- Assigned to you
- Reference: %s
- This card's information could not be read.
- Site health
- Open Site Health
- No PHP fatal errors in the last 7 days
- Last PHP fatal error: %s
- Last PHP fatal error: %1$s, in %2$s
- Healthy
- CoreX's health checks pass
- WordPress environment: %s
- Open the Security Center
- Sign-in attempts are not limited
- Login address hidden
- Recovery active: login address reachable
- Login address reachable
- %1$s of %2$s active / %1$s of %2$s active
- CoreX add-ons on this site
- Blocks going live
- Blocked
- Not counted
- %1$s of %2$s ready / %1$s of %2$s ready
- %1$s of %2$s / %1$s of %2$s
- Nothing blocks going live
- %s check still blocks going live / %s checks still block going live
- Site and content
- Security and hosting
- Privacy page published
- No privacy policy page chosen
- The privacy policy page is not published
- Terms page published
- Choose a terms page
- The terms page is not published
- Brand set
- No company name or admin logo
- No company name
- No admin logo
- Company name and admin logo set
- The kit's starter pages are not applied yet
- The kit's starter pages are applied
- Front page
- A static front page is chosen
- The theme has its own front-page template
- No static front page or front-page template
- Chosen and published
- Test email sent
- Needs Corex Mail
- No test email sent in the last %d day / No test email sent in the last %d days
- A test email was sent in the last %d day / A test email was sent in the last %d days
- Login protection on
- Login protection is off
- Login protection is on
- Removed when you save.
- Remove the saved value? %s
- This page is not available
- It belongs to an add-on that is not installed on this site, or it does not exist. Choose another Settings page.
- You can read these settings but not change them.
- Cloudflare gave an answer CoreX cannot read
- Cloudflare says the token is %s
- not active
- Cloudflare refused the token
- Cloudflare could not be reached from this server
- Google could not be reached from this server
- Google refused the key
- the key's quota is used up for today
- the key may not use the PageSpeed Insights API
- Google answered with HTTP %d
- The site key and the secret key are missing, so forms are protected by the honeypot only.
- The secret key is missing, so forms are protected by the honeypot only.
- The site key is missing, so the provider's check cannot load on your forms, and protected forms refuse what visitors send.
- No PageSpeed key is saved, so performance checks share Google's keyless quota and are refused when it runs out.
- The Cloudflare account ID is missing, so the security scan cannot run. Add it to use the token.
- The Cloudflare API token is missing, so the security scan cannot run. Add it to use the account ID.
- Save a key first, then test it.
- Last tested on %s: working
- Last test failed on %1$s: %2$s
- Not tested yet
- this provider has no test here
- Set in .env
- Set by the network
- A value is saved
- No value saved
- Remove the saved value when I save
- Only a site administrator can change how the whole site delivers mail.
- %s is off. These settings apply when it is on.
- PageSpeed Insights key
- Asks Google whether it accepts the key, without running a measurement.
- Cloudflare API token
- Asks Cloudflare whether it accepts the token. The account ID is checked when a scan runs.
- Test connection
- No changes
- You have unsaved changes on this page. Leave without saving them?
- The save did not go through, so nothing was changed. Your edits are still here: try again.
- Nothing was saved: the form did not say which Settings page it came from. Open the page again and save from there.
- %s was removed.
- General and appearance
- Your company's details, how the CoreX admin looks, and the widgets on the WordPress dashboard.
- Who CoreX email is from, where replies and form notifications go, and how an email looks.
- The spam check in front of CoreX forms.
- Corex Captcha
- Media
- WebP copies of uploaded images, and converting the images already in the library.
- Corex Media
- The keys Insights uses for performance checks and the security scan.
- Where questions from the Guides screen are sent.
- Advanced
- Facts about this server and WordPress, for whoever supports the site.
- Used across the site chrome, footer, and the Setup Wizard brand step.
- The call-to-action button label used by kit patterns (e.g. "Get a quote").
- Comma-separated profile URLs.
- Admin logo
- Used on the CoreX admin shell and the WordPress login screen.
- Login logo link
- Where the logo on the login screen takes people. This is the logo's link, not the sign-in address. Leave blank for this site's home page.
- Admin footer text
- Replaces the wp-admin footer text. Leave blank for "Powered by Corex".
- Terms page
- The page with your site's terms and conditions. The launch checklist checks that it is published.
- CoreX admin appearance
- Light
- Dark
- System follows your operating-system colour scheme.
- Mail
- Form notification recipient
- Captcha driver
- Honeypot
- Choose a provider, Honeypot (no keys), or None to disable. reCAPTCHA scores a visitor without showing anything; Turnstile and hCaptcha show their widget above a protected form’s button.
- Site key
- The public reCAPTCHA site key.
- Create reCAPTCHA keys
- The public Cloudflare Turnstile site key.
- Get Turnstile keys
- The public hCaptcha site key.
- hCaptcha site key docs
- Secret key
- The captcha check stops until a new secret key is saved. Forms keep the honeypot.
- The private reCAPTCHA secret key.
- The private Cloudflare Turnstile secret key.
- The private hCaptcha secret key.
- hCaptcha secret key docs
- reCAPTCHA v3 score threshold
- 0.0–1.0. Defaults to 0.3 — conservative for ordinary low-traffic sites. Watch real traffic before raising it; a higher threshold rejects more legitimate visitors. Only protected CoreX forms are covered.
- reCAPTCHA v3 score docs
- reCAPTCHA v3 action (global default)
- Optional. Each form derives its own action from its slug (corex_form_&lt;slug>); a form can override it. Leave blank unless you need one shared action.
- reCAPTCHA v3 action docs
- Allowed hostnames
- Comma-separated exact hostnames a verification may originate from (e.g. example.com, staging.example.com). Leave blank to allow this site’s own host only. Matched exactly, never by partial match. The secret key never reaches the browser.
- Server support
- Live image-support on this server. Also reported under Tools → Site Health.
- Convert uploads to WebP
- On upload, write a WebP sibling next to the original. Originals are always kept.
- WebP quality
- 1–100. 82 is a good balance of size and quality.
- Minimum size saving (%)
- A WebP is only served when it is at least this much smaller than the original. Default 5.
- Convert JPEG uploads
- Convert PNG uploads
- PageSpeed Insights API key
- Performance checks go back to Google's shared keyless quota, which is refused when it runs out.
- Used for performance checks.
- Get a PageSpeed Insights API key
- The Cloudflare security scan stops until a new token is saved.
- Used for the security/readiness scan.
- Create a Cloudflare API token
- Cloudflare account ID
- Found in the Cloudflare dashboard under your account home / API section.
- Find your Cloudflare account ID
- Dashboard
- Attention widget
- Lists your unread CoreX notifications on the WordPress dashboard. Hidden automatically when you have none.
- Development widget
- Shows the operating mode and its warnings. Only ever appears while the site is in Development.
- The PHP runtime this site is on.
- Environment type
- None chosen
- Site administrators always hold every CoreX ability, so this role cannot be denied it.
- This applies to you, and only a site administrator can change their own access.
- This is a Critical ability: only a site administrator can allow it or set it to inherit.
- Site administrators hold every CoreX ability. A Deny stored here before is kept and ignored.
- Site administrators hold every CoreX ability.
- Locked: this ability comes only with site administration and cannot be set for a role.
- CoreX abilities by role
- Site administrator
- Edit %s
- Editing %s
- What each role can do
- Back to the grid
- A role plugin is active. CoreX manages only its own abilities here; WordPress capabilities stay with that plugin.
- A form email also has each of its fields as {{ submission.&lt;field> }}, by the field’s key. A variable an email does not supply is left out when it is sent, and named in its log entry.
- Sample: %s
- Sending
- Rejected
- Bounced
- Opened
- Sent without: %s
- Show the email
- As HTML
- The email "%s" as it would have arrived
- This email has no plain-text version.
- Only a site administrator can open a captured email.
- Turning delivery on or off, choosing its provider and choosing which mail is captured change how the whole site sends mail, so a site administrator does them.
- Select a template on the Templates tab to see it as it is sent.
- Sample values
- Change a value to see the email with it. Nothing here is saved.
- This template uses no variables.
- How the email is shown
- Right to left
- Drawn by the server as it is sent, with sample values. Test send sends this email.
- The email as it is sent
- Drawing the email…
- Made from the HTML: paragraphs, line breaks, list items and every link’s address are kept.
- Written by hand on the Templates tab.
- Sent as the plain-text version
- Open it in the delivery log
- The test is the email the Preview tab shows, with its sample values and “[Test]” before its subject. No real submission is used.
- Send the test to
- Send test
- From name (optional)
- From address (optional)
- Left empty, the email leaves from the sender in Settings → Mail. The variables a template uses are read from its subject and body.
- Reset to the shipped version?
- Reset to shipped version
- Keep my version
- %s goes back to the words CoreX ships, as a new version. Your edited versions stay in its history.
- Saved, with something to check
- The shipped version is back, as a new version.
- Unpublish this flow?
- Unpublish
- The form will disappear from every page that shows it, until you publish it again. Visitors who send it in the meantime are told it is not available.
- Editing flow
- Publish changes
- Live version is up to date
- %d submission that was assigned to nobody was given to its form’s owner when CoreX was updated. Nothing was deleted. / %d submissions that were assigned to nobody were given to their form’s owner when CoreX was updated. Nothing was deleted.
- Live: version %1$d, published %2$s by %3$s
- Live: version %1$d, published %2$s
- Flows
- Teams
- Availability
- Versions
- When the form takes answers, and what visitors read when it does not. Saved here, it applies at once, without publishing.
- Schedule
- Each is optional. Dates and times are in the site’s time zone, %s.
- Each is optional. Dates and times are in the site’s time zone.
- Opens on
- Closes on
- Stop after this many responses
- Marked tests and responses in the trash do not count.
- When the form is closed
- Another way to get in touch
- A link
- An email address
- Link text
- Link text (optional)
- Web address
- Save availability
- Availability not saved yet
- Availability saved
- Confirmation to the person who filled it in
- Notification to your team
- Alert when an email fails
- This template uses %s, which this form does not supply. Each is left out when the email is sent.
- Send it without them
- Bind stored Email Studio templates and choose who each email goes to.
- An address
- The submitter
- Routed owner
- Their answer to
- This form asks for no email address
- %s (no address)
- If Routed owner has no address, send to
- The site administrator
- Most people a role can reach
- Who Routed owner is
- Working out who Routed owner is…
- No longer offered: choose another
- Who handles a submission
- Route each submission to a responsible person or team. Rules are evaluated top-down; the first match wins. When a rule’s target names nobody, the fallback takes it, then the flow’s owner.
- Fallback: all other submissions
- Routing target
- No teams yet: create one on the Teams view
- Choose…
- Question whose answer chooses the owner
- Choose a question…
- Who gets each answer
- Answer
- An answer
- Add answer
- After submission
- No actions are registered on this site. A developer adds them with the corex_flow_actions hook.
- Each chosen action runs once, after the submission is stored. Its result is on the submission’s history; a failing action stops nothing.
- This flow runs an action the site no longer has. Untick it before publishing:
- Remove it
- Runs validation, protection, storage, routing, inbox, timeline, email and actions stages without entering ordinary metrics.
- Each version that was published. Copy one into the draft to work from it; it goes live only when you publish.
- This flow has not been published yet.
- Author not recorded
- Hide
- Copy into draft
- %d question / %d questions
- Team saved.
- Team removed.
- Route submissions to a team instead of one person. Each member sees what is assigned to the team in their inbox.
- New team
- Loading teams…
- No teams yet.
- No members
- %1$d member: %2$s / %1$d members: %2$s
- Remove this team?
- Remove team
- Submissions already assigned to %s stay with it, and managers still see them. New submissions routed to it go to the flow’s fallback.
- Edit team
- Save team
- Team name
- Members
- Availability saved.
- Open (new or in progress)
- Unread only

**plugins/corex-core** (40)

- CoreX admin
- Data models
- Access CoreX admin
- Access every CoreX product area.
- Manage access
- Grant and review CoreX abilities.
- Manage forms and flows
- Create, publish, and operate forms and flows.
- Manage submissions
- Read and process form submissions.
- Manage data
- Explore, export, and change supported CoreX data.
- Manage data models
- Import, migrate, and configure CoreX data models.
- Export personal data
- Take personal data out of the site in an exported file.
- Manage Email Studio
- Configure, test, and inspect CoreX email.
- Manage Blog Pro
- Configure editorial and analytics workflows.
- Manage operations and security
- Configure operating modes, security, and recovery.
- Install releases
- Install a release of the site from the admin, and go back to the one before.
- Run dangerous actions
- Confirm production-impacting and destructive operations.
- Manage notifications
- Review, resolve, and configure CoreX notifications.
- Manage setup
- Run and resume the CoreX setup workflow.
- Manage settings
- Configure CoreX product settings.
- See CoreX screens without changing them
- Open Overview, Settings and Insights read-only.
- You can read this screen but not change it. Changes need “%s”, which only site administrators hold: it cannot be given to a role or requested.
- You can read this screen but not change it. Changes need “%s”, which site administrators hold and an owner can give in Access & Abilities.
- %s pages
- This CoreX screen needs a CoreX ability you do not hold.
- Set in the site's .env file
- Locked by the network administrator

**plugins/corex-forms** (69)

- Only editors see this: the form "%s" is not taking answers. Reopen it or change its dates in Forms & Flows.
- Only editors see this: the form in this block is not published.
- Open it in Forms & Flows
- The alternative link must be a web address that starts with http:// or https://.
- The alternative link needs a label.
- The alternative email must be an email address.
- The closing date must be after the opening date.
- The response cap must be a whole number of at least 1.
- The closed message is too long. Keep it under 500 characters.
- "If Routed owner has no address" must be an email address.
- The action "%s" is not available on this site.
- The template %1$s uses %2$s, which this form does not supply. Remove it from the template, or confirm sending the email without it in the Emails tab.
- Flow owner
- Page owner
- Field value
- Not open yet
- This form opens on %s.
- This form is closed.
- Opens on %s
- Closed on %s
- Reached %d response / Reached %d responses
- A former user
- Live version is up to date.
- Flow version was not found.
- This flow changed since you opened it. Reload to see the latest draft.
- The person this route names no longer has an account.
- The form's owner no longer has an account.
- This route uses a target CoreX no longer offers.
- The team "%s" no longer exists.
- The role "%s" no longer exists.
- The page the form was sent from is not known.
- The page the form was sent from has no author.
- The question "%s" was not answered.
- The answer "%1$s" to "%2$s" has no owner.
- The person this answer names no longer has an account.
- The routing extension "%s" is not available.
- The routing extension "%1$s" failed: %2$s
- The routing extension "%s" named nobody.
- Assigned to %s, the form owner: it was assigned to nobody before this update.
- Routed owner is a role with more than %1$d person; sent to %2$s / Routed owner is a role with more than %1$d people; sent to %2$s
- Routed owner had no address; sent to %s
- Team: %s
- Role: %s
- Form owner (%s)
- a former user
- Page author
- By the answer to "%s"
- An extension that is not available
- A target CoreX no longer offers
- Rule %d
- No rule matches
- This target is not complete yet.
- Worked out for each submission.
- Nobody could be assigned; it is in the inbox for whoever manages submissions.
- Assigned to %s instead.
- The team was not found.
- The team could not be removed. Try again.
- A team needs a name.
- A team name can be at most 100 characters.
- No flow actions to run.
- Flow actions ran.
- Flow actions ran; at least one failed. The timeline says which.
- The action "%s" is not available.
- This is a test.
- %1$s failed: %2$s
- %s: done.
- Not sent: this site is not set up to send email yet.
- Submission route resolved, but it names nobody.
- Many people are sending this form right now. Please try again in a moment.

</details>

## The checklist

For each screenshot, for a sample of at least 50 catalogue entries of the first draft chosen across the add-ons,
and for every entry drafted on 2026-11-11 (above):

| Check | What passes |
|---|---|
| Letters are joined | No Arabic word is drawn as separate letters; the face is IBM Plex Sans Arabic, not a fallback |
| Punctuation side | The Arabic comma ، semicolon ؛ and question mark ؟ are used, and full stops and colons sit at the left end of a right-to-left line |
| Numerals | Western digits (0 to 9), as the glossary says; a number keeps its place in the sentence |
| Mixed text | An address, a file name, a version or a product name inside an Arabic sentence reads in the right order |
| Line spacing | Running text is not cramped (line height 1.8; headings 1.3) |
| Truncation | No label, button or table heading is cut off or wraps into another control at 375px |
| Terms | Each term is the glossary's; a term not in the glossary is added to it |
| Meaning and register | Clear Modern Standard Arabic, addressed to the site owner as أنت; no literal or awkward rendering |

## Record

| Date | Reviewer | Release candidate | Screens read | Entries sampled | Result | Corrections (commit) |
|---|---|---|---|---|---|---|
| | | | | | | |

When the review passes, `PROJECT-STATUS.md`'s item "Arabic typography has layout proof, not type proof" is
replaced by a line that cites this record.
