<?php
/**
 * FrontAccounting CRM Adapter Events
 *
 * Event classes for CRM functionality in the FA adapter layer.
 *
 * @package Ksfraser\FA\CRM
 * @version 1.0.0
 */

namespace Ksfraser\FA\CRM;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Base CRM Event
 */
abstract class CRMEvent implements StoppableEventInterface
{
    protected bool $propagationStopped = false;

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}

/**
 * CRM Customer Created Event
 */
class CRMCustomerCreatedEvent extends CRMEvent
{
    private CRMCustomer $customer;

    public function __construct(CRMCustomer $customer)
    {
        $this->customer = $customer;
    }

    public function getCustomer(): CRMCustomer
    {
        return $this->customer;
    }

    public function getDebtorNo(): string
    {
        return $this->customer->getDebtorNo();
    }
}

/**
 * CRM Customer Updated Event
 */
class CRMCustomerUpdatedEvent extends CRMEvent
{
    private CRMCustomer $customer;

    public function __construct(CRMCustomer $customer)
    {
        $this->customer = $customer;
    }

    public function getCustomer(): CRMCustomer
    {
        return $this->customer;
    }

    public function getDebtorNo(): string
    {
        return $this->customer->getDebtorNo();
    }
}

/**
 * CRM Customer Deleted Event
 */
class CRMCustomerDeletedEvent extends CRMEvent
{
    private string $debtorNo;
    private array $customerData;

    public function __construct(string $debtorNo, array $customerData)
    {
        $this->debtorNo = $debtorNo;
        $this->customerData = $customerData;
    }

    public function getDebtorNo(): string
    {
        return $this->debtorNo;
    }

    public function getCustomerData(): array
    {
        return $this->customerData;
    }
}

/**
 * CRM Contact Created Event
 */
class CRMContactCreatedEvent extends CRMEvent
{
    private CRMContact $contact;

    public function __construct(CRMContact $contact)
    {
        $this->contact = $contact;
    }

    public function getContact(): CRMContact
    {
        return $this->contact;
    }

    public function getContactId(): int
    {
        return $this->contact->getId();
    }

    public function getDebtorNo(): string
    {
        return $this->contact->getDebtorNo();
    }
}

/**
 * CRM Contact Updated Event
 */
class CRMContactUpdatedEvent extends CRMEvent
{
    private CRMContact $contact;

    public function __construct(CRMContact $contact)
    {
        $this->contact = $contact;
    }

    public function getContact(): CRMContact
    {
        return $this->contact;
    }

    public function getContactId(): int
    {
        return $this->contact->getId();
    }

    public function getDebtorNo(): string
    {
        return $this->contact->getDebtorNo();
    }
}

/**
 * CRM Contact Deleted Event
 */
class CRMContactDeletedEvent extends CRMEvent
{
    private int $contactId;
    private string $debtorNo;
    private array $contactData;

    public function __construct(int $contactId, string $debtorNo, array $contactData)
    {
        $this->contactId = $contactId;
        $this->debtorNo = $debtorNo;
        $this->contactData = $contactData;
    }

    public function getContactId(): int
    {
        return $this->contactId;
    }

    public function getDebtorNo(): string
    {
        return $this->debtorNo;
    }

    public function getContactData(): array
    {
        return $this->contactData;
    }
}

/**
 * CRM Opportunity Created Event
 */
class CRMOpportunityCreatedEvent extends CRMEvent
{
    private CRMOpportunity $opportunity;

    public function __construct(CRMOpportunity $opportunity)
    {
        $this->opportunity = $opportunity;
    }

    public function getOpportunity(): CRMOpportunity
    {
        return $this->opportunity;
    }

    public function getOpportunityId(): int
    {
        return $this->opportunity->getId();
    }

    public function getDebtorNo(): ?string
    {
        return $this->opportunity->getDebtorNo();
    }

    public function getEstimatedValue(): ?float
    {
        return $this->opportunity->getEstimatedValue();
    }
}

/**
 * CRM Opportunity Updated Event
 */
class CRMOpportunityUpdatedEvent extends CRMEvent
{
    private CRMOpportunity $opportunity;

    public function __construct(CRMOpportunity $opportunity)
    {
        $this->opportunity = $opportunity;
    }

    public function getOpportunity(): CRMOpportunity
    {
        return $this->opportunity;
    }

    public function getOpportunityId(): int
    {
        return $this->opportunity->getId();
    }

    public function getDebtorNo(): ?string
    {
        return $this->opportunity->getDebtorNo();
    }
}

/**
 * CRM Opportunity Deleted Event
 */
class CRMOpportunityDeletedEvent extends CRMEvent
{
    private int $opportunityId;
    private array $opportunityData;

    public function __construct(int $opportunityId, array $opportunityData)
    {
        $this->opportunityId = $opportunityId;
        $this->opportunityData = $opportunityData;
    }

    public function getOpportunityId(): int
    {
        return $this->opportunityId;
    }

    public function getOpportunityData(): array
    {
        return $this->opportunityData;
    }

    public function getDebtorNo(): ?string
    {
        return $this->opportunityData['debtor_no'] ?? null;
    }

    public function getOpportunityName(): string
    {
        return $this->opportunityData['opportunity_name'];
    }
}

/**
 * CRM Opportunity Status Changed Event
 */
class CRMOpportunityStatusChangedEvent extends CRMEvent
{
    private CRMOpportunity $opportunity;
    private string $oldStatus;
    private string $newStatus;

    public function __construct(CRMOpportunity $opportunity, string $oldStatus, string $newStatus)
    {
        $this->opportunity = $opportunity;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    public function getOpportunity(): CRMOpportunity
    {
        return $this->opportunity;
    }

    public function getOpportunityId(): int
    {
        return $this->opportunity->getId();
    }

    public function getDebtorNo(): ?string
    {
        return $this->opportunity->getDebtorNo();
    }

    public function getOldStatus(): string
    {
        return $this->oldStatus;
    }

    public function getNewStatus(): string
    {
        return $this->newStatus;
    }

    public function isClosed(): bool
    {
        return in_array($this->newStatus, ['closed_won', 'closed_lost']);
    }

    public function isWon(): bool
    {
        return $this->newStatus === 'closed_won';
    }

    public function isLost(): bool
    {
        return $this->newStatus === 'closed_lost';
    }
}

/**
 * CRM Communication Created Event
 */
class CRMCommunicationCreatedEvent extends CRMEvent
{
    private CRMCommunication $communication;

    public function __construct(CRMCommunication $communication)
    {
        $this->communication = $communication;
    }

    public function getCommunication(): CRMCommunication
    {
        return $this->communication;
    }

    public function getCommunicationId(): int
    {
        return $this->communication->getId();
    }

    public function getDebtorNo(): ?string
    {
        return $this->communication->getDebtorNo();
    }

    public function getCommunicationType(): string
    {
        return $this->communication->getCommunicationType();
    }

    public function isInbound(): bool
    {
        return $this->communication->isInbound();
    }

    public function isOutbound(): bool
    {
        return $this->communication->isOutbound();
    }
}

/**
 * CRM Communication Updated Event
 */
class CRMCommunicationUpdatedEvent extends CRMEvent
{
    private CRMCommunication $communication;

    public function __construct(CRMCommunication $communication)
    {
        $this->communication = $communication;
    }

    public function getCommunication(): CRMCommunication
    {
        return $this->communication;
    }

    public function getCommunicationId(): int
    {
        return $this->communication->getId();
    }

    public function getDebtorNo(): ?string
    {
        return $this->communication->getDebtorNo();
    }
}

/**
 * CRM Communication Completed Event
 */
class CRMCommunicationCompletedEvent extends CRMEvent
{
    private CRMCommunication $communication;

    public function __construct(CRMCommunication $communication)
    {
        $this->communication = $communication;
    }

    public function getCommunication(): CRMCommunication
    {
        return $this->communication;
    }

    public function getCommunicationId(): int
    {
        return $this->communication->getId();
    }

    public function getDebtorNo(): ?string
    {
        return $this->communication->getDebtorNo();
    }

    public function getCommunicationType(): string
    {
        return $this->communication->getCommunicationType();
    }

    public function getDurationMinutes(): ?int
    {
        return $this->communication->getDurationMinutes();
    }

    public function isFollowUpRequired(): bool
    {
        return $this->communication->isFollowUpRequired();
    }
}

/**
 * CRM Follow-up Required Event
 */
class CRMFollowUpRequiredEvent extends CRMEvent
{
    private CRMCommunication $communication;
    private ?string $followUpDate;

    public function __construct(CRMCommunication $communication, ?string $followUpDate)
    {
        $this->communication = $communication;
        $this->followUpDate = $followUpDate;
    }

    public function getCommunication(): CRMCommunication
    {
        return $this->communication;
    }

    public function getCommunicationId(): int
    {
        return $this->communication->getId();
    }

    public function getDebtorNo(): ?string
    {
        return $this->communication->getDebtorNo();
    }

    public function getFollowUpDate(): ?string
    {
        return $this->followUpDate;
    }

    public function getPriority(): string
    {
        return $this->communication->getPriority();
    }
}
