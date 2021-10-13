@extends('layouts.app')

@section('content')

    @include('layouts.partials.banner', [
    'title' => 'Books',
    'breadcrumbs' => [
        route('reader.languages') => 'Reader',
        route('reader.bibles', ['language_id' => $language_id]) => 'Bibles',
        '#' => 'Books'
    ]
])

    <table class="table" width="100%">
        <thead>
            <th>Vernacular Title</th>
            <th>English Title</th>
            <th>Chapters</th>
        </thead>
        <tbody>
        @foreach($books as $book)
            <tr>
                <td>{{ $book->vernacular_title }}</td>
                <td>{{ $book->name }}</td>
                <td>
                    @foreach(explode(',',$book->existing_chapters) as $chapter)
                        <a href="{{ route('reader.chapter',['id' => $bible_id,'book'=>$book->id,'chapter'=>$chapter]) }}">{{ $chapter }}</a>
                    @endforeach
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>


@endsection