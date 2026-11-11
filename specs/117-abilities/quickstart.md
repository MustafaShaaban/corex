# Quickstart: checking spec 117 by hand

On a scratch site with every CoreX add-on active (never a client's site):

1. As an administrator, open CoreX → Access & Abilities → Role matrix. Press **Edit** on Editor, set "Manage forms
   and flows" to Allow, preview and apply. The Editor column reads "Allowed (set here)" on that row.
2. Sign in as an editor. The CoreX menu shows Forms & Flows and Guides. Forms & Flows opens and a flow saves.
   `admin.php?page=corex-settings-config` answers 403 with the denied page naming "Manage settings".
3. As the administrator, try to Deny anything on the Administrator column: no Deny is offered. A Deny stored there
   before this release is listed on the Overview tab as not applied.
4. Grant an editor "See CoreX screens without changing them". As that editor, Settings opens with a "Read only"
   chip, every field marked unavailable and saying why on focus; a save posted by hand is refused and appears on
   the Audit log tab.
5. Open the Audit log tab: the role change, the refusal and any request are listed by day, 25 a page, with filters.
6. Switch the site to Arabic: ability and area names are Arabic (once translated), keys appear only in tooltips.
