<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\FormDetailResource;
use App\Http\Resources\V1\FormResource;
use App\Models\Customer;
use App\Models\Entry;
use App\Models\Form;
use App\Models\FormSetting;
use App\Notifications\EntryReceived;
use App\Support\Fields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Forms (فرم‌ها) built in the panel, for the site to draw and send, version 1.
 *
 * Reading a form gives its fields: each has a key, a type, a label, and the settings the
 * site needs to draw and check it. Sending posts the answers keyed by field key; they are
 * checked against the same fields and land in the panel inbox. Only active forms are sent
 * and take messages. No token is needed; a customer Bearer token links the message to the
 * customer. Routes live under /v1/forms.
 *
 * Extending:
 * - A new field type belongs in App\Support\Fields, which both reading and sending use.
 */
class FormController extends Controller
{
    /**
     * Active forms, by title.
     *
     * The whole list comes back by default. Send page or per_page (up to 100, 50 when left out)
     * to get it a page at a time, with links and meta. q searches the title.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::CEILING],
        ]);

        $query = Form::query()
            ->open()
            ->when($data['q'] ?? null, fn (Builder $query, string $text) => $query->where('title', 'like', '%'.$text.'%'))
            ->orderBy('title')
            ->orderBy('id');

        return FormResource::collection($this->paged($query, $data));
    }

    /**
     * One active form by its slug, with the fields to draw.
     *
     * Each field has the same keys. type is one of text, textarea, email, phone, number, url,
     * date, select, radio, checkboxes, checkbox, file, or paragraph. A paragraph is text to
     * show between fields and takes no answer, so its key is null. options lists the choices
     * of select, radio, and checkboxes; multiple is true when the answer is a list. min and
     * max bound the length of text and textarea, or the value of number. accept lists the
     * file extensions a file field takes and size its largest file in kilobytes. condition,
     * when set, shows the field only while the answer of condition.field equals
     * condition.value (or contains it, for a list; "1" means a ticked checkbox). width is full
     * or half. multipart is true when the form has a file field, so the site sends
     * multipart/form-data. honeypot is the name of a hidden input the site adds and sends
     * empty. An unknown or inactive slug gets 404.
     */
    public function show(string $slug): FormDetailResource
    {
        return new FormDetailResource($this->form($slug));
    }

    /**
     * Sends the answers of an active form. They wait in the panel inbox.
     *
     * The body has one value per field, named by the field's key: text for text, textarea,
     * email, phone, url, and radio; a number for number; YYYY-MM-DD for date; true or 1 for a
     * ticked checkbox; a list of options for checkboxes and a multiple select; and a file for
     * file, sent as multipart/form-data. Persian digits are accepted in phone, number, and
     * date. Answers are cleaned before they are checked: HTML tags and invisible characters
     * are removed, extra spaces collapse, and only a textarea keeps line breaks. url takes
     * http and https only, date lies between 1900 and 2100, and a file must match its
     * allowed types by both extension and content. A field hidden by its condition, and any
     * key the form does not have, is ignored. Send the empty hidden input named by honeypot
     * in the form; a filled one gets 422. Errors come back as 422, keyed by field key, in
     * Persian. message is the text to show the visitor. An unknown or inactive slug gets
     * 404, and sending more often than the forms settings allow gets 429.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $form = $this->form($slug);

        if (filled($request->input(Fields::HONEYPOT))) {
            throw ValidationException::withMessages([Fields::HONEYPOT => 'ارسال پذیرفته نشد.']);
        }

        $fields = $form->definition();
        $input = Fields::prepare($fields, $request->all());

        $data = Validator::make(
            $input,
            Fields::rules($fields, $input, FormSetting::current()->size),
            Fields::messages(),
            Fields::attributes($fields),
        )->validate();

        $customer = Auth::guard('customer')->user();
        $agent = Str::limit(Fields::sanitize((string) $request->userAgent()), 250, '');

        $entry = $form->entries()->create([
            'customer_id' => $customer instanceof Customer ? $customer->getKey() : null,
            'answers' => Fields::answers($fields, $data, $form),
            'status' => Entry::NEW,
            'ip' => $request->ip(),
            'agent' => $agent !== '' ? $agent : null,
            'source' => Fields::link($request->headers->get('referer')),
        ]);

        $addresses = $form->addresses();

        if ($addresses !== []) {
            Notification::route('mail', $addresses)->notify(new EntryReceived($entry));
        }

        return response()->json([
            'message' => $form->thanks(),
            'data' => [
                'id' => $entry->id,
                'created_at' => $entry->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * The active form a route points at; an unknown or inactive slug gets 404.
     */
    private function form(string $slug): Form
    {
        $form = Form::query()->open()->where('slug', $slug)->first();

        abort_if($form === null, 404, 'فرم پیدا نشد.');

        return $form;
    }
}
