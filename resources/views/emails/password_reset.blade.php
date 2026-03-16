<!DOCTYPE html>
<html lang="{{ $htmlLang ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <style>
        h2 {
            text-align: center;
        }
        .button {
            display: block;
            width:150px;
            margin:10px auto;
            background-color:#222;
            color:#f1f1f1;
            padding:10px;
            text-align: center;
            text-decoration: none;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <h2>{{ trans('auth.reset_email_heading', [], $language) }}</h2>
    <p>{{ trans('auth.reset_email_body', ['project_name' => $project->name], $language) }}</p>
    <p>
        <a class="button" href="{{ $project->url_reset ?? config('app.url').'/password/reset' }}/{{ $user->token}}">
            {{ trans('auth.reset_email_action', [], $language) }}
        </a>
    </p>
</body>
</html>