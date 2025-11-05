<?php

namespace App\Livewire;

use Livewire\Component;
use App\Libraries\MultiDB;
use Livewire\Attributes\On;
use App\DataMapper\InvoiceSync;
use App\Models\QuoteInvitation;
use App\Models\CreditInvitation;
use App\Livewire\Flow2\DocuNinja;
use App\Models\InvoiceInvitation;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Cache;
use App\Livewire\Flow2\DocuNinjaLoader;
use App\Models\PurchaseOrderInvitation;
use App\Utils\Traits\WithSecureContext;

class Sign extends Component
{
    use WithSecureContext;

    public $invitation_ids;
    public $entity_type;
    public $db;

    public $current_index = 0;
    public $invitation_id;
    public $docu_ninja_ready = false;
    public $signature_accepted = false;

    public $request_hash;
    public $initializing = true;
    
    public function mount()
    {
        MultiDB::setDb($this->db);

        $this->invitation_id = reset($this->invitation_ids);
        
        $this->bulkSetContext([
            'db' => $this->db,
            'entity_type' => $this->entity_type,
            'invitation_id' => reset($this->invitation_ids),
        ]);
        $this->request_hash = $this->request_hash;

        // Find and set the first invitation that needs signing
        $this->initializeNextInvitation();
        
        // Mark initialization as complete after a brief delay to prevent immediate event firing
        $this->initializing = false;
    }

    /**
     * Check if an invitation is already signed (skip if signed)
     * 
     * @param int $invitation_id
     * @return bool True if already signed, false if needs signing
     */
    protected function isAlreadySigned(int $invitation_id): bool
    {
        $invitation = $this->resolveInvitationModel($invitation_id);

        if (!$invitation) {
            return true; // Skip if invitation not found
        }

        // Check if already signed (regular signature)
        if ($invitation->signature_base64) {
            return true;
        }

        // Check if DocuNinja signature is already completed
        $entity = $invitation->{$this->entity_type};
        if (isset($entity->sync->dn_completed) && $entity->sync->dn_completed) {
            return true;
        }

        return false;
    }

    /**
     * Resolve the invitation model based on entity_type
     * 
     * @param int $invitation_id
     * @return InvoiceInvitation|QuoteInvitation|CreditInvitation|PurchaseOrderInvitation|null
     */
    protected function resolveInvitationModel(int $invitation_id)
    {
        return match($this->entity_type) {
            'invoice' => InvoiceInvitation::withTrashed()->with('contact.client', 'invoice')->find($invitation_id),
            'quote' => QuoteInvitation::withTrashed()->with('contact.client', 'quote')->find($invitation_id),
            'credit' => CreditInvitation::withTrashed()->with('contact.client', 'credit')->find($invitation_id),
            'purchase_order' => PurchaseOrderInvitation::withTrashed()->with('contact.vendor', 'purchase_order')->find($invitation_id),
            default => InvoiceInvitation::withTrashed()->with('contact.client', 'invoice')->find($invitation_id),
        };
    }

    /**
     * Find the next invitation that needs signing (not already signed)
     * 
     * @return int|null
     */
    protected function findNextInvitationRequiringSignature()
    {
        for ($i = $this->current_index; $i < count($this->invitation_ids); $i++) {
            $invitation_id = $this->invitation_ids[$i];
            
            // Skip if already signed, otherwise return it
            if (!$this->isAlreadySigned($invitation_id)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Initialize the next invitation that requires signing
     * 
     * @return void
     */
    protected function initializeNextInvitation(): void
    {
        $nextIndex = $this->findNextInvitationRequiringSignature();

        if ($nextIndex !== null) {
            $this->current_index = $nextIndex;
            $this->invitation_id = $this->invitation_ids[$this->current_index];
            $this->signature_accepted = false;

            $this->bulkSetContext([
                'db' => $this->db,
                'invitation_id' => $this->invitation_id,
                'entity_type' => $this->entity_type,
            ]);
        }
    }

    /**
     * Move to the next invitation that requires signing
     * 
     * @return bool True if moved to next invitation, false if no more invitations
     */
    protected function moveToNextInvitation(): bool
    {
        // Search from current_index + 1 (the next invitation after the one we just signed)
        $nextIndex = $this->findNextInvitationRequiringSignatureFromIndex($this->current_index + 1);

        if ($nextIndex !== null) {
            $this->current_index = $nextIndex;
            $this->invitation_id = $this->invitation_ids[$this->current_index];
            
            // Reset all component state to force fresh initialization
            $this->signature_accepted = false;
            $this->docu_ninja_ready = false;

            // Update context with new invitation_id - this will be picked up by child components on remount
            $this->bulkSetContext([
                'db' => $this->db,
                'invitation_id' => $this->invitation_id,
                'entity_type' => $this->entity_type,
            ]);

            // The key change (componentUniqueId includes invitation_id) will force Livewire 
            // to completely unmount and remount the child component with fresh state

            return true;
        }

        return false;
    }

    #[Computed()]
    public function component(): ?string
    {
        // Only show component if we have an invitation_id and haven't accepted signature yet
        if (!$this->invitation_id || $this->signature_accepted) {
            return null;
        }
        
        if ($this->docu_ninja_ready) {
            return DocuNinja::class;
        } else {
            return DocuNinjaLoader::class;
        }
    }

    #[On('docuninja-signature-captured')]
    public function docuNinjaSignatureCaptured()
    {
        // Prevent processing if we're still initializing (to avoid race conditions during mount)
        if ($this->initializing) {
            return;
        }
        
        if (!$this->signature_accepted) {
            $this->signature_accepted = true;
        }

        // Check if there's another document that needs signing
        // This method will verify the current invitation is signed before proceeding
        $this->checkAndMoveToNextDocument();
    }

    #[On('docuninja-loader-ready')]
    public function docuninjaLoaderReady()
    {
        $this->docu_ninja_ready = true;    
    }

    /**
     * Check if there's another document available and move to it if needed
     * 
     * @return \Illuminate\Http\RedirectResponse|void
     */
    protected function checkAndMoveToNextDocument()
    {
        // First, verify the current invitation is actually signed before proceeding
        // This prevents premature form submission if the signature hasn't been saved yet
        if (!$this->isAlreadySigned($this->invitation_id)) {
            // Current invitation is not signed yet, wait for it to be saved
            // This should not happen, but adding as a safety check
            return;
        }
        
        // Move to the next invitation that requires signing
        if ($this->moveToNextInvitation()) {
            // There's another document to sign
            // The component will automatically re-render with the new invitation_id
        }
        else {
            // No more documents to sign, proceed to payment
            $this->processPayment();
        }
    }
    
    /**
     * Find the next invitation that needs signing starting from a specific index
     * 
     * @param int $startIndex The index to start searching from
     * @return int|null
     */
    protected function findNextInvitationRequiringSignatureFromIndex(int $startIndex)
    {
        for ($i = $startIndex; $i < count($this->invitation_ids); $i++) {
            $invitation_id = $this->invitation_ids[$i];
            
            // Skip if already signed, otherwise return it
            if (!$this->isAlreadySigned($invitation_id)) {
                return $i;
            }
        }

        return null;
    }


    public function processPayment()
    {

        $request_array = Cache::get($this->request_hash);
        $request_array['docuninja_active'] = false;

        Cache::put($this->request_hash, $request_array, 60 * 60 * 24);

        $this->redirectRoute('client.payments.process', ['request_hash' => $this->request_hash]);
       
    }

    #[Computed()]
    public function componentUniqueId(): string
    {
        // Include invitation_id in the key to force remount when moving to next invitation
        return "sign-" . $this->invitation_id . "-" . $this->current_index;
    }

    public function render()
    {
        return render('components.livewire.sign');
    }
}