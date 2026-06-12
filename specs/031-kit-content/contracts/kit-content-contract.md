# Contract: kit content seeding

## Blueprint::pages()
Returns `list<array{title:string,slug:string,content:string,front?:bool}>`; default `[]`. Content references
only existing `corex/*` patterns/blocks.

## KitPagePlanner::toCreate(declared, existingSlugs)
Returns the subset of `declared` whose `slug` ∉ `existingSlugs`. Pure, deterministic.

## BlueprintActivator::seedPages(pages)
For each planned page: `wp_insert_post({post_title,post_name,post_status:'publish',post_type:'page',post_content})`;
`update_post_meta(id,'_corex_kit_page','1')`; if `front`, set `show_on_front=page` + `page_on_front=id`. Append
the id to `corex_kit_seeded_pages`. Idempotent (the planner skips existing slugs).

## Soft reset removal
Reads `corex_kit_seeded_pages`; trashes each id; clears the option. Removes only marked kit pages.
