<div class="w-full">
    {{-- Trigger button --}}
    @if(!$this->show_component)
        <button 
            wire:click="showComponent" 
            class="button button-primary bg-primary"
            type="button"
        >
            {{ ctrans('texts.sign_now') }}
        </button>
    @endif

    {{-- Fullscreen modal --}}
    @if($this->show_component && ($this->component === 'App\Livewire\Flow2\DocuNinjaLoader' || $this->component === 'App\Livewire\Flow2\DocuNinja'))
        <div 
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75"
            wire:keydown.escape="$set('show_component', false)" {{-- close on ESC --}}
        >
            <div class="bg-white w-full h-full relative overflow-auto p-4">
                
                {{-- Close button --}}
                <button 
                    class="absolute top-4 right-4 text-gray-700 hover:text-gray-900"
                    wire:click="$set('show_component', false)"
                >&times;</button>

                {{-- Livewire component --}}
                @livewire($this->component, ['invitation_id' => $this->invitation_id], key($this->componentUniqueId()))
            </div>
        </div>
    @endif
</div>
