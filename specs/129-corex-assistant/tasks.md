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

## Slice 2: Perego's revision 2 (DECISIONS #609 to #616)

- [ ] T014 Paid fallbacks: catalogue (`openai`, `anthropic`), `ModelClient` for the Messages API and GPT-5's fields,
  `PaidAnswers`, the router's paid skip and count, `assistant.paid_cap` (+ tests)
- [ ] T015 Owner-set order and a switch per service: Settings type `order`, defaults table, `assistant.order`,
  `assistant.<id>.on`, `AssistantSettings::chain()` in order, cooldown failures, the panel (+ tests)
- [ ] T016 Booking hours: Settings fields, `BookingHours`, `GET corex/v1/assistant/slots`, `Form::prepare()`, the
  callback's slot in both zones, the slot picker (+ tests)
- [ ] T017 Required fields that follow the chosen channel: `required_if` in the forms engine, the callback's rules,
  the form's channel first (+ tests)
- [ ] T018 Limits and per-language branding on the screen (Settings type `number`, `assistant.limit.*`,
  `assistant.brand.<language>.*`), and a real-size "Check services" (+ tests)
- [ ] T019 Local check on the worktree's WordPress; Playwright with axe
- [ ] T020 Guards; docs, CHANGELOG (Client impact), DECISIONS, PROGRESS; pull request; Perego's hand-over note
