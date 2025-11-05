<?php

namespace App\Livewire\Flow2;

use Livewire\Component;
use App\Libraries\MultiDB;
use Livewire\Attributes\On;
use Livewire\Attributes\Lazy;
use App\DataMapper\InvoiceSync;
use App\Models\QuoteInvitation;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use App\Models\PurchaseOrderInvitation;
use App\Utils\Traits\WithSecureContext;

class DocuNinjaLoader extends Component
{
    use WithSecureContext;

    public $invitation_id;
    public $isLoading = true;
    public $error = null;
    public $isReady = false;

    public $entity_type;

    public function mount()
    {
        nlog($this->getContext());
        // Set database context
        MultiDB::setDb($this->getContext()['db']);   
     }

    public function loadDocuNinjaData()
    {
        try {
            
            $entity_type = $this->getContext()['entity_type'];
            $this->entity_type = $entity_type;
            $invitation = $this->resolveInvitationModel($this->getContext()['invitation_id']);

            if (!$invitation) {
                throw new \Exception('Invoice invitation not found');
            }

            // Check if DocuNinja is already completed
            if(isset($invitation->{$entity_type}->sync->dn_completed) && $invitation->{$entity_type}->sync->dn_completed){
                $this->dispatch('docuninja-signature-captured'); 
                $this->isLoading = false;
                return;
            }
            elseif(!$invitation->can_sign && $invitation->{$entity_type}->invitations()->where('can_sign', true)->count() >= 1)
            {
                // A special edge case exists for old invitations where the can_sign flag is not set.
                // For this scenario - the first user to view the doc, will have can_sign set to true.
                $this->error = 'You are not authorized to sign this document.';
                $this->isLoading = false;
                return;
            }
            elseif($invitation->can_sign &&
                isset($invitation->{$entity_type}->sync) && 
                $dn_invite = $invitation->{$entity_type}->sync->getInvitation($invitation->key)){
                
                $signable = [
                    'document_id' => $dn_invite['dn_id'],
                    'document_invitation_id' => $dn_invite['dn_invitation_id'],
                    'sig' => $dn_invite['dn_sig'],
                    'success' => !$invitation->{$entity_type}->sync->dn_completed,
                ];
                
            }
            // Generate new DocuNinja signable data
            else{
                
                //Handle edge case where the can_sign flag is not set for any invites
                if(!$invitation->can_sign){
                    $invitation->can_sign = true;
                    $invitation->saveQuietly();
                }

                $signable = $invitation->{$entity_type}->service()->getDocuNinjaSignable($invitation);
                
                $sync = new InvoiceSync(qb_id: '', dn_completed: false);
                $sync->addInvitation(
                    $signable['invitation_key'],
                    $signable['document_id'],
                    $signable['document_invitation_id'],
                    $signable['sig']
                );
                $invitation->{$entity_type}->sync = $sync;
                $invitation->{$entity_type}->save();
                
            }

            // Check if signing is not successful (already completed or error)
            if(!$signable['success']){
                $this->dispatch('docuninja-signature-captured');
                return;
            }
            
            // Mark as ready and dispatch event to parent
            $this->isReady = true;
            $this->isLoading = false;
            
            // Dispatch event to InvoicePay to switch to DocuNinja component
            $this->dispatch('docuninja-loader-ready', [
                'invitation_id' => $this->invitation_id,
                'document_id' => $signable['document_id'],
                'document_invitation_id' => $signable['document_invitation_id'],
                'sig' => $signable['sig'],
                'company_key' => $invitation->company->company_key
            ]);

        } catch (\Exception $e) {
                      
            $this->error = 'Failed to load DocuNinja data: ' . $e->getMessage();
            $this->isLoading = false;
        }
    }

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

    // Method to retry loading if there was an error
    public function retryLoading()
    {
        $this->error = null;
        $this->isLoading = true;
        $this->isReady = false;
        
        $this->loadDocuNinjaData();
    }

    public function render()
    {
        return view('portal.ninja2020.flow2.docu-ninja-loader');
    }
}
