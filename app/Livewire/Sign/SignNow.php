<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire\Sign;

use Livewire\Component;
use App\Libraries\MultiDB;
use App\Models\QuoteInvitation;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use Livewire\Attributes\Computed;
use App\Models\PurchaseOrderInvitation;
use Livewire\Attributes\On;
use App\Utils\Traits\WithSecureContext;

class SignNow extends Component
{
    use WithSecureContext;

    public $invitation_ids;

    public $entity_type;

    public $db;

    public $docu_ninja_ready = false;

    public $signature_accepted = false;

    public $invitation_id;

    public $current_index = 0;

    public $show_component = false;

    public $set_docuninja = true;

    public function mount()
    {
        MultiDB::setDb($this->db);

        $this->bulkSetContext([
            'db' => $this->db,
            'invitation_id' => reset($this->invitation_ids),
            'entity_type' => $this->entity_type,
        ]);
        
        // Find and set the first invitation that requires signing
        $this->initializeNextInvitation();
    }

    /**
     * Show the signature component when button is clicked
     * 
     * @return void
     */
    public function showComponent(): void
    {
        $this->show_component = true;
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
            'purchase_order' => PurchaseOrderInvitation::withTrashed()->with('contact.vendor', 'purchaseOrder')->find($invitation_id),
            default => InvoiceInvitation::withTrashed()->with('contact.client', 'invoice')->find($invitation_id),
        };
    }

    /**
     * Find the next invitation that needs signing (not already signed)
     * 
     * @return int|null
     */
    protected function findNextInvitationRequiringSignature(): ?int
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
        $this->current_index++;
        $nextIndex = $this->findNextInvitationRequiringSignature();

        if ($nextIndex !== null) {
            $this->current_index = $nextIndex;
            $this->invitation_id = $this->invitation_ids[$this->current_index];
            $this->signature_accepted = false;
            $this->docu_ninja_ready = false;

            $this->bulkSetContext([
                'db' => $this->db,
                'invitation_id' => $this->invitation_id,
                'entity_type' => $this->entity_type,
            ]);

            return true;
        }

        return false;
    }

    #[Computed()]
    public function component(): ?string
    {

        if(!$this->signature_accepted) {
            if ($this->docu_ninja_ready) {
                return \App\Livewire\Flow2\DocuNinja::class;
            } else {

                return \App\Livewire\Flow2\DocuNinjaLoader::class;
            }
        }

        return null;

    }

    #[On('signature-captured')]
    public function signatureCaptured($base64)
    {
        $this->signature_accepted = true;
        $invite = $this->resolveInvitationModel($this->invitation_id);
        
        if ($invite) {
            $invite->signature_base64 = $base64;
            
            // Get timezone offset based on entity type
            // Purchase orders use company timezone directly, others use client timezone
            if ($this->entity_type === 'purchase_order') {
                $timezoneOffset = $invite->company->timezone_offset();
            } else {
                $timezoneOffset = $invite->contact->client->timezone_offset();
            }
            
            $invite->signature_date = now()->addSeconds($timezoneOffset);
            $this->setContext('signature', $base64); // $this->context['signature'] = $base64;
            $invite->save();
        }

        // Check if there's another document that needs signing
        $this->checkAndMoveToNextDocument();
    }

    #[On('docuninja-signature-captured')]
    public function docuNinjaSignatureCaptured()
    {
        $this->signature_accepted = true;

        // Check if there's another document that needs signing
        $this->checkAndMoveToNextDocument();
    }

    #[On('docuninja-loader-ready')]
    public function docuninjaLoaderReady()
    {
        nlog("docuninja loader ready");
        $this->docu_ninja_ready = true;    
    }

    /**
     * Check if there's another document available and move to it if needed
     * 
     * @return void
     */
    protected function checkAndMoveToNextDocument(): void
    {
        // Move to the next invitation that requires signing
        if ($this->moveToNextInvitation()) {
            // There's another document to sign
            // The component will automatically re-render with the new invitation_id
            // Reset docu_ninja flags if needed
            $nextInvitation = $this->resolveInvitationModel($this->invitation_id);
           
        }
        else{
            $this->show_component = false;

        }

        // If no more documents, signature_accepted stays true and component will hide
    }


    #[Computed()]
    public function componentUniqueId(): string
    {
        return "sign-" . md5(microtime());
    }

    public function render()
    {
        return render('components.livewire.sign-now');
    }
}
