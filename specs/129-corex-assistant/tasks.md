# Tasks: CoreX Assistant (spec 129)

- [x] T001 Add-on skeleton: plugin file, README, package.json, webpack config; registry, catalogue, composer,
  workspaces, CODEOWNERS
- [x] T002 Settings: section `assistant`, page `assistant`; secrets saved with autoload off (+ tests)
- [x] T003 Chain: Provider, Failure, Cooldowns, ModelClient, ChatRouter, AssistantSettings (+ tests)
- [x] T004 Knowledge: Knowledge, KnowledgeProvider, SiteKnowledge (+ tests)
- [x] T005 Reply: Instructions, ReplyShaper (+ tests)
- [x] T006 Limits: VisitorKey, VisitorLimits, VisitorLock, AssistantLimitsMiddleware (+ tests)
- [x] T007 AssistantService, AssistantController, route registration (+ tests)
- [x] T008 Strings, AssistantButton, callback form (+ tests)
- [x] T009 Admin panel: status, today's count, Check services (+ tests)
- [x] T010 Front end: view.js, chat.js, dom.js, handoff.js, dial-codes.js, styles (+ Jest)
- [x] T011 Local check on the worktree's WordPress: off by default, button, lazy chat, stubbed model reply,
  hand-off stored, RTL, settings page
- [x] T012 Guards (clean-code, wp, test, docs); docs page, CHANGELOG (Client impact), PROGRESS, DECISIONS
- [x] T013 Pull request

## Next slice (owed): Perego's revision 2

- [ ] T014 Paid fallbacks: OpenAI (chat completions) and Claude (Messages API), off until keyed, with a daily cap
  for paid answers
- [ ] T015 Owner-set order and a switch per service on Settings → Assistant
- [ ] T016 Booking hours: time slots in the visitor's time zone, both zones in the team's email
- [ ] T017 Required fields that follow the chosen channel
- [ ] T018 Limits and per-language branding edited on the screen; a real-size "Check services" question
