<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TaskTemplate;
use App\Services\Gemini;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    /**
     * Public listing: approved + public events only, matching the mock's
     * getPublicEvents() (App.jsx:82). Optionally sorted by real distance
     * from the visitor's browser-reported coordinates - events with no
     * lat/lng (organizer skipped the map picker) sort last rather than
     * being excluded, since they're still valid events.
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ]);

        $events = Event::where('status', 'approved')
            ->where('is_private', false)
            ->orderByDesc('created_at')
            ->get();

        if (! empty($data['lat']) && ! empty($data['lng'])) {
            $events = $events->each(function ($event) use ($data) {
                if ($event->lat !== null && $event->lng !== null) {
                    $event->distance_km = round(
                        $this->haversineKm((float) $data['lat'], (float) $data['lng'], (float) $event->lat, (float) $event->lng),
                        1
                    );
                }
            })->sortBy(fn ($event) => $event->distance_km ?? INF)->values();
        }

        return response()->json($events);
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Admin listing: every event regardless of status/visibility, matching
     * the mock's getEvents() (App.jsx:80). Eager-loads tasks so the admin
     * event detail page's checklist section (Front-end/src/pages/admin/
     * EventDetail.jsx) has data without a second request.
     */
    public function adminIndex()
    {
        return response()->json(Event::with('tasks')->orderByDesc('created_at')->get());
    }

    /**
     * Looked up by slug (matches the frontend's /events/:slug share links,
     * plan §5). Not restricted by status/privacy here - the mock's getEvent()
     * has no such restriction either; a private event's obscurity comes from
     * its unguessable private_link, not from hiding the show endpoint.
     */
    public function show(string $slug)
    {
        $event = Event::with('tasks')->where('slug', $slug)->firstOrFail();

        return response()->json($event);
    }

    /**
     * AI-written first draft for the event wizard's "Generate description"
     * button - genuinely calls Gemini (the button previously returned one
     * of three canned paragraphs client-side and never touched an API).
     * No Event exists yet at this point in the wizard, so this takes the
     * in-progress form fields directly rather than an event ID.
     */
    public function generateDescription(Request $request)
    {
        abort_unless(Gemini::isConfigured(), 503, 'AI descriptions aren\'t configured on this server yet - set GEMINI_API_KEY.');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'industry' => ['nullable', 'string', 'max:100'],
        ]);
        $title = $data['title'];
        $type = $data['type'] ?? 'event';
        $industry = $data['industry'] ?? 'general';

        $prompt = <<<PROMPT
        Write a promotional description for an event, to appear on its public registration page.

        Title: {$title}
        Type: {$type}
        Industry: {$industry}

        3-5 sentences. Inviting and specific to the title/type/industry given, not generic filler. No headings, no markdown, plain prose only. Return only the description text itself.
        PROMPT;

        return response()->json(['description' => Gemini::generate($prompt, 512)]);
    }

    /**
     * Cover/pubmat image upload for the event wizard - event.image has
     * always just been a URL string (organizers could already paste a link
     * to an image hosted elsewhere), so this doesn't touch the Event model
     * at all: it stores the file on the "public" disk (the same one
     * payment screenshots use - swap to R2/S3 there and this follows for
     * free) and hands back a URL the frontend drops into that same field.
     * No event needs to exist yet, same reasoning as generateDescription.
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'], // 5MB, matches the payment-screenshot limit
        ]);

        $path = $request->file('image')->store('event-images', 'public');

        return response()->json(['url' => Storage::disk('public')->url($path)]);
    }

    public function store(Request $request)
    {
        $saveAsDraft = $request->boolean('save_as_draft');

        if ($saveAsDraft) {
            // A half-finished wizard sends '' for fields the organizer hasn't
            // reached yet; treat those as absent rather than failing "must be
            // a valid date" etc. on a save that's explicitly meant to be partial.
            $request->merge(collect($request->all())->map(fn ($v) => $v === '' ? null : $v)->all());
        }

        $data = $this->validated($request, draft: $saveAsDraft);

        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['status'] = $saveAsDraft ? 'draft' : 'pending';
        $data['user_id'] = $request->user()->id;

        if ($data['is_private'] ?? false) {
            $data['private_link'] = $data['private_link'] ?? Str::random(16);
        } else {
            $data['private_link'] = null;
        }

        $taskTemplateId = $data['task_template_id'] ?? null;
        unset($data['task_template_id']);

        $event = Event::create($data);

        if ($taskTemplateId) {
            $template = TaskTemplate::find($taskTemplateId);
            foreach (($template->tasks ?? []) as $label) {
                $event->tasks()->create(['label' => $label, 'done' => false]);
            }
        }

        return response()->json($event->load('tasks'), 201);
    }

    public function update(Request $request, Event $event)
    {
        $this->authorizeOwner($request, $event);

        $data = $this->validated($request, sometimes: true);

        if (array_key_exists('title', $data) && $data['title'] !== $event->title) {
            $data['slug'] = $this->uniqueSlug($data['title'], $event->id);
        }

        if (array_key_exists('is_private', $data)) {
            if ($data['is_private']) {
                $data['private_link'] = $event->private_link ?? Str::random(16);
            } else {
                $data['private_link'] = null;
            }
        }

        unset($data['task_template_id']);

        $event->update($data);

        return response()->json($event->fresh('tasks'));
    }

    public function destroy(Request $request, Event $event)
    {
        $this->authorizeOwner($request, $event);

        $event->delete();

        return response()->json(['message' => 'Event deleted.']);
    }

    public function approve(Request $request, Event $event)
    {
        $this->authorizeAdmin($request);

        $event->update(['status' => 'approved']);

        return response()->json($event);
    }

    public function reject(Request $request, Event $event)
    {
        $this->authorizeAdmin($request);

        $event->update(['status' => 'rejected']);

        return response()->json($event);
    }

    /**
     * Moves a draft into the approval queue once the organizer is happy
     * with it - the other half of "save as draft" in store(). Drafts are
     * allowed to be incomplete, but a "pending" event has to have the
     * fields an approver and, later, a registrant actually need.
     */
    public function submit(Request $request, Event $event)
    {
        $this->authorizeOwner($request, $event);

        abort_unless($event->status === 'draft', 422, 'Only draft events can be submitted.');

        $missing = collect([
            'type' => 'Event type',
            'venue' => 'Venue',
            'date' => 'Date',
            'start_time' => 'Start time',
            'end_time' => 'End time',
        ])->filter(fn ($label, $field) => blank($event->{$field}));

        if ($event->capacity < 1) {
            $missing['capacity'] = 'Capacity';
        }

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(
                $missing->mapWithKeys(fn ($label, $field) => [$field => ["{$label} is required before submitting."]])->all()
            );
        }

        $event->update(['status' => 'pending']);

        return response()->json($event);
    }

    /**
     * Lets the organizer close out an event once it's actually over, rather
     * than waiting on the date/end-time to pass - useful for wrapping early,
     * fixing a wrong end time, or just not waiting on the scheduler.
     */
    public function complete(Request $request, Event $event)
    {
        $this->authorizeOwner($request, $event);

        abort_unless($event->status === 'approved', 422, 'Only approved events can be marked completed.');

        $event->update(['status' => 'completed']);

        return response()->json($event);
    }

    /**
     * Clones an event as a new draft - lets an organizer reuse a recurring
     * event's whole setup (venue, custom fields, pricing, checklist...)
     * instead of rebuilding it from scratch in the wizard.
     */
    public function duplicate(Request $request, Event $event)
    {
        $copy = $event->replicate(['slug', 'private_link', 'status', 'ai_summary', 'ai_summary_generated_at']);
        $copy->title = "{$event->title} (Copy)";
        $copy->slug = $this->uniqueSlug($copy->title);
        $copy->status = 'draft';
        $copy->private_link = $event->is_private ? Str::random(16) : null;
        $copy->user_id = $request->user()->id;
        $copy->save();

        foreach ($event->tasks as $task) {
            $copy->tasks()->create(['label' => $task->label, 'done' => false]);
        }

        return response()->json($copy->load('tasks'), 201);
    }

    public function addTask(Request $request, Event $event)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
        ]);

        $task = $event->tasks()->create(['label' => $data['label'], 'done' => false]);

        return response()->json($task, 201);
    }

    public function toggleTask(Event $event, \App\Models\EventTask $task)
    {
        abort_unless($task->event_id === $event->id, 404);

        $task->update(['done' => ! $task->done]);

        return response()->json($task);
    }

    /**
     * Only the organizer who created an event can edit, delete, or submit
     * it - unlike guest-list/check-in operations, which stay open to any
     * organizer (shared front-desk tasks). Events with no owner (legacy rows
     * from before this existed) stay editable by anyone rather than
     * becoming permanently locked.
     */
    private function authorizeOwner(Request $request, Event $event): void
    {
        abort_if(
            $event->user_id !== null && $event->user_id !== $request->user()->id,
            403,
            'Only the organizer who created this event can do that.'
        );
    }

    /**
     * Approving/rejecting is restricted to admins - previously any
     * organizer could approve or reject any event, including their own.
     * Deliberately not an owner check: an admin approving their own event
     * is fine, since the point is limiting *who* can approve, not requiring
     * a second person for the same event.
     */
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only an admin can do that.');
    }

    /**
     * $sometimes relaxes required fields to optional-if-present (PUT-style
     * partial updates). $draft relaxes them further still - a draft only
     * needs a title, so an organizer can save half-finished work instead of
     * losing it by having to fill in every field in one sitting.
     */
    private function validated(Request $request, bool $sometimes = false, bool $draft = false): array
    {
        $required = $draft ? ['sometimes', 'nullable'] : ($sometimes ? ['sometimes', 'required'] : ['required']);
        // Title is the one field a draft can't skip - always required when
        // creating, optional-if-present ($sometimes) only on PUT updates.
        $titleRequired = $sometimes ? ['sometimes', 'required'] : ['required'];

        return $request->validate([
            'title' => [...$titleRequired, 'string', 'max:255'],
            'type' => [...$required, 'string', 'max:255'],
            'is_private' => ['sometimes', 'boolean'],
            'private_link' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'venue' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'date' => [...$required, 'date'],
            'start_time' => [...$required, 'string', 'max:5'],
            'end_time' => [...$required, 'string', 'max:5'],
            'organized_by' => ['sometimes', 'nullable', 'string', 'max:255'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:255'],
            'capacity' => [...$required, 'integer', 'min:0'],
            'feedback_enabled' => ['sometimes', 'boolean'],
            'image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'requires_certificate' => ['sometimes', 'boolean'],
            'pricing' => ['sometimes', Rule::in(['free', 'paid', 'walk-in'])],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'allow_walk_ins' => ['sometimes', 'boolean'],
            'socials' => ['sometimes', 'nullable', 'array'],
            'privacy_policy_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'custom_fields' => ['sometimes', 'nullable', 'array'],
            'feedback_questions' => ['sometimes', 'nullable', 'array'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'task_template_id' => ['sometimes', 'nullable', 'integer', 'exists:task_templates,id'],
        ]);
    }

    private function uniqueSlug(string $title, ?int $ignoreEventId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 2;

        while (
            Event::where('slug', $slug)
                ->when($ignoreEventId, fn ($q) => $q->where('id', '!=', $ignoreEventId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
