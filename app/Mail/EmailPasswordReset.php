<?php

namespace App\Mail;

use App\Models\User\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailPasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    protected $user;
    protected $project;
    protected $language;

    /**
     * Create a new message instance.
     *
     * @param User $user
     * @param Project $project
     * @param string $language
     */
    public function __construct($user, $project, $language = 'eng')
    {
        if (!$project) {
            $project = new Project();
            $project->iso = 'eng';
            $project->name = 'Digital Bible Platform';
        }

        $this->user = $user;
        $this->project = $project;
        $this->language = $language;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $htmlLang = $this->toBcp47($this->language);

        return $this->view('emails.password_reset')
                    ->from(config('mail.from.address'), $this->project->name)
                    ->subject(trans('auth.reset_email_heading', [], $this->language))
                    ->with(['user' => $this->user, 'project' => $this->project, 'language' => $this->language, 'htmlLang' => $htmlLang]);
    }

    private function toBcp47(string $locale): string
    {
        $map = [
            'eng' => 'en', 'spa' => 'es', 'fre' => 'fr', 'arb' => 'ar',
            'ben' => 'bn', 'dan' => 'da', 'deu' => 'de', 'ell' => 'el',
            'fas' => 'fa', 'hau' => 'ha', 'heb' => 'he', 'hin' => 'hi',
            'ind' => 'id', 'ibo' => 'ig', 'ita' => 'it', 'jpn' => 'ja',
            'jav' => 'jv', 'kor' => 'ko', 'kmr' => 'ku', 'lat' => 'la',
            'lit' => 'lt', 'mal' => 'ml', 'zlm' => 'ms', 'nld' => 'nl',
            'por' => 'pt', 'ron' => 'ro', 'rus' => 'ru', 'swe' => 'sv',
            'tam' => 'ta', 'tel' => 'te', 'tha' => 'th', 'tur' => 'tr',
            'urd' => 'ur', 'vie' => 'vi', 'yor' => 'yo', 'cmn' => 'zh',
            'aze' => 'az', 'ukr' => 'uk',
        ];

        return $map[$locale] ?? 'en';
    }
}
