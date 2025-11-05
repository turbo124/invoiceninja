<div class="w-full">
    @if($this->component === 'App\Livewire\Flow2\DocuNinjaLoader' || $this->component === 'App\Livewire\Flow2\DocuNinja')
    {{-- Full width for docuninja component --}}
        @if($errors->any())
        <div class="alert alert-error">
            <ul>
                @foreach($errors->all() as $error)
                    <li class="text-sm">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @php
            session()->forget('errors');
        @endphp

        @livewire($this->component, ['invitation_id' => $this->invitation_id], key($this->componentUniqueId()))
@endif
</div>