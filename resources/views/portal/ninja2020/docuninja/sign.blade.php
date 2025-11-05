@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.sign_now'))

@push('head')

@endpush

@section('header')
    @if($errors->any())
        <div class="alert alert-failure mb-4">
            @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif
@endsection

@section('body')
    
    @livewire('sign', [
        'invitation_ids' => $invitation_ids, 
        'entity_type' => $entity_type, 
        'db' => $db,
        'request_hash' => $request_hash,
        'docuninja_active' => true,
        ])
        
@endsection

@section('footer')
@endsection
