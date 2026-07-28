# Nobody can see an access request

Read from the source on 2026-07-28, at `60f5be9`, while building a browser test that needed to
clear a pending request between runs. The cleanup could not be written, which is how this was found.

## The claim the product makes

The denied surface tells the requester, in as many words:

> Your request is tracked in CoreX Access & Abilities so an administrator can approve or deny it.

## What actually happens

A request is created, an audit event is written, a notification is dispatched — and then:

**The REST list route returns an empty array. Always.**

```php
public function requests(WP_REST_Request $request): WP_REST_Response
{
    if ($request->get_method() === 'POST') {
        return $this->createRequest($request);
    }

    return $this->ok(['requests' => []]);
}
```

**The Access screen hands the same empty array to its React app.**

```php
wp_localize_script('corex-access', 'corexAccess', [
    ...
    'requests' => [],
    ...
]);
```

**The panel that renders them describes the plumbing instead of showing anything.**

```jsx
<h3>{ __( 'Access requests', 'corex' ) }</h3>
<p className="corex-access__muted">
    { __( 'Pending request workflow is backed by the Access REST routes.', 'corex' ) }
</p>
<ul>
    { ( config?.requests || [] ).map( ( request ) => (
        <li key={ request.id }>{ request.reason }</li>
    ) ) }
</ul>
```

No requester. No ability. No date. No approve. No deny.

**And `pending()` is called by nothing.**

```
$ rg 'pending\(\)' --type php
plugins/corex-core/src/Access/AccessRequestStore.php:38       the declaration
plugins/corex-config/src/Access/AccessRequestRepository.php:101   the implementation
tests/...                                                     two tests
```

The repository method that lists open requests has no production caller.

## Why this is the more serious half of spec 079

The defect this spec started from — the form posting a browser at a REST endpoint — made a
successful request *look* like a failure. This one makes a successful request *be* one. The row is
real, the audit entry is real, the notification is real, and the workflow ends there because no
screen ever reads the table.

It also makes the sentence above false, which matters more than the raw JSON page did: a person who
saw the JSON at least knew something odd had happened. A person who sees a clean confirmation is
told, accurately as far as they can tell, that an administrator will look at it.

Fixing the requester's side alone would have delivered people into that silence more convincingly
than before. So the administrator's side is in scope: the list is real, and approve and deny do what
they say.

## What already exists, and does work

`AccessService::decideRequest()` is complete — it transitions the row, grants the ability on
approval through `AccessUserDirectory::grantUserAbility()`, records an audit event and notifies.
The decision REST route is registered and capability-gated. Nothing about the decision needs
inventing; it needs a surface that can reach it.
