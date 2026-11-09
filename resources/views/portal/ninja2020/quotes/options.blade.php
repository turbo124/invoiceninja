@extends('portal.ninja2020.layout.app')

@section('meta_title', ctrans('texts.quote') . ' ' . $quote->number)

@section('body')
    @livewire('quote-options', ['quote_id' => $quote->hashed_id])
@endsection
