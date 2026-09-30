<?php

namespace App\Notifications;

use App\Models\Entry;
use App\Support\Fields;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email about a new form message, sent to the form's and the settings' addresses.
 *
 * It lists every answer and links to the message in the panel inbox. It is queued, so a
 * slow mail server never delays the visitor. Uploaded files are not attached; they are
 * downloaded from the panel. Answers are escaped for Markdown, so a visitor cannot add
 * links or formatting to the email.
 *
 * Extending:
 * - Laravel owns the via and toMail method names.
 */
class EntryReceived extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Entry  $entry  The message that just arrived.
     */
    public function __construct(public Entry $entry) {}

    /**
     * Sent by email only.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * The subject, one line per answer, and a button to the message in the panel.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $title = (string) $this->entry->form?->title;
        $mail = (new MailMessage)
            ->subject('پیام تازه از فرم «'.$title.'»')
            ->greeting('پیام تازه')
            ->line('پیامی از فرم «'.$title.'» رسید:');

        foreach ((array) $this->entry->answers as $answer) {
            $mail->line(Fields::markdown(($answer['label'] ?? $answer['key'] ?? '').': '.Fields::text($answer['value'] ?? null)));
        }

        return $mail->action('مشاهده در صندوق پیام‌ها', url('admin/inbox/'.$this->entry->getKey()));
    }
}
