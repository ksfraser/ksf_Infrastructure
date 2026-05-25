<?php
/**
 * FrontAccounting CRM Adapter Entities
 *
 * Entity classes for CRM functionality in the FA adapter layer.
 *
 * @package Ksfraser\FA\CRM
 * @version 1.0.0
 */

namespace Ksfraser\FA\CRM;

/**
 * CRM Customer Entity
 */
class CRMCustomer
{
    private string $debtorNo;
    private ?int $customerTypeId;
    private ?int $customerSegmentId;
    private ?int $territoryId;
    private ?string $customerSince;
    private ?string $website;
    private ?string $industry;
    private ?int $employeeCount;
    private ?float $annualRevenue;
    private ?string $parentCompany;
    private ?float $latitude;
    private ?float $longitude;
    private bool $ediEnabled;
    private bool $marketingOptOut;
    private string $preferredContactMethod;
    private ?string $lastContactDate;
    private ?string $nextFollowupDate;
    private ?string $accountManager;
    private string $creditRating;
    private float $paymentReliability;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(array $data)
    {
        $this->debtorNo = $data['debtor_no'];
        $this->customerTypeId = $data['customer_type_id'] ?? null;
        $this->customerSegmentId = $data['customer_segment_id'] ?? null;
        $this->territoryId = $data['territory_id'] ?? null;
        $this->customerSince = $data['customer_since'];
        $this->website = $data['website'] ?? null;
        $this->industry = $data['industry'] ?? null;
        $this->employeeCount = $data['employee_count'] ?? null;
        $this->annualRevenue = $data['annual_revenue'] ?? null;
        $this->parentCompany = $data['parent_company'] ?? null;
        $this->latitude = $data['latitude'] ?? null;
        $this->longitude = $data['longitude'] ?? null;
        $this->ediEnabled = (bool)($data['edi_enabled'] ?? false);
        $this->marketingOptOut = (bool)($data['marketing_opt_out'] ?? false);
        $this->preferredContactMethod = $data['preferred_contact_method'] ?? 'email';
        $this->lastContactDate = $data['last_contact_date'];
        $this->nextFollowupDate = $data['next_followup_date'] ?? null;
        $this->accountManager = $data['account_manager'] ?? null;
        $this->creditRating = $data['credit_rating'] ?? 'good';
        $this->paymentReliability = (float)($data['payment_reliability'] ?? 100.00);
        $this->createdAt = $data['created_at'];
        $this->updatedAt = $data['updated_at'];
    }

    public function getDebtorNo(): string
    {
        return $this->debtorNo;
    }

    public function getCustomerTypeId(): ?int
    {
        return $this->customerTypeId;
    }

    public function getCustomerSegmentId(): ?int
    {
        return $this->customerSegmentId;
    }

    public function getTerritoryId(): ?int
    {
        return $this->territoryId;
    }

    public function getCustomerSince(): ?string
    {
        return $this->customerSince;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function getIndustry(): ?string
    {
        return $this->industry;
    }

    public function getEmployeeCount(): ?int
    {
        return $this->employeeCount;
    }

    public function getAnnualRevenue(): ?float
    {
        return $this->annualRevenue;
    }

    public function getParentCompany(): ?string
    {
        return $this->parentCompany;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function isEdiEnabled(): bool
    {
        return $this->ediEnabled;
    }

    public function isMarketingOptOut(): bool
    {
        return $this->marketingOptOut;
    }

    public function getPreferredContactMethod(): string
    {
        return $this->preferredContactMethod;
    }

    public function getLastContactDate(): ?string
    {
        return $this->lastContactDate;
    }

    public function getNextFollowupDate(): ?string
    {
        return $this->nextFollowupDate;
    }

    public function getAccountManager(): ?string
    {
        return $this->accountManager;
    }

    public function getCreditRating(): string
    {
        return $this->creditRating;
    }

    public function getPaymentReliability(): float
    {
        return $this->paymentReliability;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }

    public function toArray(): array
    {
        return [
            'debtor_no' => $this->debtorNo,
            'customer_type_id' => $this->customerTypeId,
            'customer_segment_id' => $this->customerSegmentId,
            'territory_id' => $this->territoryId,
            'customer_since' => $this->customerSince,
            'website' => $this->website,
            'industry' => $this->industry,
            'employee_count' => $this->employeeCount,
            'annual_revenue' => $this->annualRevenue,
            'parent_company' => $this->parentCompany,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'edi_enabled' => $this->ediEnabled,
            'marketing_opt_out' => $this->marketingOptOut,
            'preferred_contact_method' => $this->preferredContactMethod,
            'last_contact_date' => $this->lastContactDate,
            'next_followup_date' => $this->nextFollowupDate,
            'account_manager' => $this->accountManager,
            'credit_rating' => $this->creditRating,
            'payment_reliability' => $this->paymentReliability,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }

    public function update(array $data): void
    {
        if (isset($data['customer_type_id'])) {
            $this->customerTypeId = $data['customer_type_id'];
        }
        if (isset($data['customer_segment_id'])) {
            $this->customerSegmentId = $data['customer_segment_id'];
        }
        if (isset($data['territory_id'])) {
            $this->territoryId = $data['territory_id'];
        }
        if (isset($data['website'])) {
            $this->website = $data['website'];
        }
        if (isset($data['industry'])) {
            $this->industry = $data['industry'];
        }
        if (isset($data['employee_count'])) {
            $this->employeeCount = $data['employee_count'];
        }
        if (isset($data['annual_revenue'])) {
            $this->annualRevenue = $data['annual_revenue'];
        }
        if (isset($data['parent_company'])) {
            $this->parentCompany = $data['parent_company'];
        }
        if (isset($data['latitude'])) {
            $this->latitude = $data['latitude'];
        }
        if (isset($data['longitude'])) {
            $this->longitude = $data['longitude'];
        }
        if (isset($data['edi_enabled'])) {
            $this->ediEnabled = (bool)$data['edi_enabled'];
        }
        if (isset($data['marketing_opt_out'])) {
            $this->marketingOptOut = (bool)$data['marketing_opt_out'];
        }
        if (isset($data['preferred_contact_method'])) {
            $this->preferredContactMethod = $data['preferred_contact_method'];
        }
        if (isset($data['last_contact_date'])) {
            $this->lastContactDate = $data['last_contact_date'];
        }
        if (isset($data['next_followup_date'])) {
            $this->nextFollowupDate = $data['next_followup_date'];
        }
        if (isset($data['account_manager'])) {
            $this->accountManager = $data['account_manager'];
        }
        if (isset($data['credit_rating'])) {
            $this->creditRating = $data['credit_rating'];
        }
        if (isset($data['payment_reliability'])) {
            $this->paymentReliability = (float)$data['payment_reliability'];
        }
        $this->updatedAt = date('Y-m-d H:i:s');
    }
}

/**
 * CRM Contact Entity
 */
class CRMContact
{
    private int $id;
    private string $debtorNo;
    private ?int $contactRoleId;
    private string $firstName;
    private string $lastName;
    private ?string $title;
    private ?string $department;
    private ?string $phone;
    private ?string $mobile;
    private ?string $email;
    private ?string $address;
    private ?string $notes;
    private bool $isPrimary;
    private bool $inactive;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->debtorNo = $data['debtor_no'];
        $this->contactRoleId = $data['contact_role_id'] ?? null;
        $this->firstName = $data['first_name'];
        $this->lastName = $data['last_name'];
        $this->title = $data['title'] ?? null;
        $this->department = $data['department'] ?? null;
        $this->phone = $data['phone'] ?? null;
        $this->mobile = $data['mobile'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->address = $data['address'] ?? null;
        $this->notes = $data['notes'] ?? null;
        $this->isPrimary = (bool)($data['is_primary'] ?? false);
        $this->inactive = (bool)($data['inactive'] ?? false);
        $this->createdAt = $data['created_at'];
        $this->updatedAt = $data['updated_at'];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDebtorNo(): string
    {
        return $this->debtorNo;
    }

    public function getContactRoleId(): ?int
    {
        return $this->contactRoleId;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getMobile(): ?string
    {
        return $this->mobile;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function isInactive(): bool
    {
        return $this->inactive;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'debtor_no' => $this->debtorNo,
            'contact_role_id' => $this->contactRoleId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'title' => $this->title,
            'department' => $this->department,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'address' => $this->address,
            'notes' => $this->notes,
            'is_primary' => $this->isPrimary,
            'inactive' => $this->inactive,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }

    public function update(array $data): void
    {
        if (isset($data['contact_role_id'])) {
            $this->contactRoleId = $data['contact_role_id'];
        }
        if (isset($data['first_name'])) {
            $this->firstName = $data['first_name'];
        }
        if (isset($data['last_name'])) {
            $this->lastName = $data['last_name'];
        }
        if (isset($data['title'])) {
            $this->title = $data['title'];
        }
        if (isset($data['department'])) {
            $this->department = $data['department'];
        }
        if (isset($data['phone'])) {
            $this->phone = $data['phone'];
        }
        if (isset($data['mobile'])) {
            $this->mobile = $data['mobile'];
        }
        if (isset($data['email'])) {
            $this->email = $data['email'];
        }
        if (isset($data['address'])) {
            $this->address = $data['address'];
        }
        if (isset($data['notes'])) {
            $this->notes = $data['notes'];
        }
        if (isset($data['is_primary'])) {
            $this->isPrimary = (bool)$data['is_primary'];
        }
        if (isset($data['inactive'])) {
            $this->inactive = (bool)$data['inactive'];
        }
        $this->updatedAt = date('Y-m-d H:i:s');
    }
}

/**
 * CRM Opportunity Entity
 */
class CRMOpportunity
{
    private int $id;
    private string $opportunityName;
    private ?string $debtorNo;
    private ?int $contactId;
    private ?string $salesPerson;
    private ?string $opportunityType;
    private string $status;
    private ?float $estimatedValue;
    private ?float $probability;
    private ?string $expectedCloseDate;
    private ?string $notes;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->opportunityName = $data['opportunity_name'];
        $this->debtorNo = $data['debtor_no'] ?? null;
        $this->contactId = $data['contact_id'] ?? null;
        $this->salesPerson = $data['sales_person'] ?? null;
        $this->opportunityType = $data['opportunity_type'] ?? null;
        $this->status = $data['status'];
        $this->estimatedValue = $data['estimated_value'] ?? null;
        $this->probability = $data['probability'] ?? null;
        $this->expectedCloseDate = $data['expected_close_date'];
        $this->notes = $data['notes'] ?? null;
        $this->createdAt = $data['created_at'];
        $this->updatedAt = $data['updated_at'];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getOpportunityName(): string
    {
        return $this->opportunityName;
    }

    public function getDebtorNo(): ?string
    {
        return $this->debtorNo;
    }

    public function getContactId(): ?int
    {
        return $this->contactId;
    }

    public function getSalesPerson(): ?string
    {
        return $this->salesPerson;
    }

    public function getOpportunityType(): ?string
    {
        return $this->opportunityType;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getEstimatedValue(): ?float
    {
        return $this->estimatedValue;
    }

    public function getProbability(): ?float
    {
        return $this->probability;
    }

    public function getWeightedValue(): float
    {
        if ($this->estimatedValue === null || $this->probability === null) {
            return 0.0;
        }
        return $this->estimatedValue * ($this->probability / 100);
    }

    public function getExpectedCloseDate(): ?string
    {
        return $this->expectedCloseDate;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['closed_won', 'closed_lost']);
    }

    public function isWon(): bool
    {
        return $this->status === 'closed_won';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'opportunity_name' => $this->opportunityName,
            'debtor_no' => $this->debtorNo,
            'contact_id' => $this->contactId,
            'sales_person' => $this->salesPerson,
            'opportunity_type' => $this->opportunityType,
            'status' => $this->status,
            'estimated_value' => $this->estimatedValue,
            'probability' => $this->probability,
            'expected_close_date' => $this->expectedCloseDate,
            'notes' => $this->notes,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }

    public function update(array $data): void
    {
        if (isset($data['opportunity_name'])) {
            $this->opportunityName = $data['opportunity_name'];
        }
        if (isset($data['debtor_no'])) {
            $this->debtorNo = $data['debtor_no'];
        }
        if (isset($data['contact_id'])) {
            $this->contactId = $data['contact_id'];
        }
        if (isset($data['sales_person'])) {
            $this->salesPerson = $data['sales_person'];
        }
        if (isset($data['opportunity_type'])) {
            $this->opportunityType = $data['opportunity_type'];
        }
        if (isset($data['status'])) {
            $this->status = $data['status'];
        }
        if (isset($data['estimated_value'])) {
            $this->estimatedValue = $data['estimated_value'];
        }
        if (isset($data['probability'])) {
            $this->probability = $data['probability'];
        }
        if (isset($data['expected_close_date'])) {
            $this->expectedCloseDate = $data['expected_close_date'];
        }
        if (isset($data['notes'])) {
            $this->notes = $data['notes'];
        }
        $this->updatedAt = date('Y-m-d H:i:s');
    }
}

/**
 * CRM Communication Entity
 */
class CRMCommunication
{
    private int $id;
    private ?string $debtorNo;
    private ?int $contactId;
    private string $communicationType;
    private string $direction;
    private ?string $subject;
    private ?string $message;
    private ?string $emailFrom;
    private ?string $emailTo;
    private ?string $phoneNumber;
    private ?int $durationMinutes;
    private string $status;
    private ?string $scheduledDate;
    private ?string $completedDate;
    private ?string $assignedTo;
    private string $priority;
    private bool $followUpRequired;
    private ?string $followUpDate;
    private ?string $notes;
    private ?string $emailMessageId;
    private ?string $attachmentPath;
    private ?string $createdBy;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->debtorNo = $data['debtor_no'] ?? null;
        $this->contactId = $data['contact_id'] ?? null;
        $this->communicationType = $data['communication_type'];
        $this->direction = $data['direction'] ?? 'outbound';
        $this->subject = $data['subject'] ?? null;
        $this->message = $data['message'] ?? null;
        $this->emailFrom = $data['email_from'] ?? null;
        $this->emailTo = $data['email_to'] ?? null;
        $this->phoneNumber = $data['phone_number'] ?? null;
        $this->durationMinutes = $data['duration_minutes'] ?? null;
        $this->status = $data['status'] ?? 'completed';
        $this->scheduledDate = $data['scheduled_date'];
        $this->completedDate = $data['completed_date'] ?? null;
        $this->assignedTo = $data['assigned_to'] ?? null;
        $this->priority = $data['priority'] ?? 'medium';
        $this->followUpRequired = (bool)($data['follow_up_required'] ?? false);
        $this->followUpDate = $data['follow_up_date'] ?? null;
        $this->notes = $data['notes'] ?? null;
        $this->emailMessageId = $data['email_message_id'] ?? null;
        $this->attachmentPath = $data['attachment_path'] ?? null;
        $this->createdBy = $data['created_by'] ?? null;
        $this->createdAt = $data['created_at'];
        $this->updatedAt = $data['updated_at'];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDebtorNo(): ?string
    {
        return $this->debtorNo;
    }

    public function getContactId(): ?int
    {
        return $this->contactId;
    }

    public function getCommunicationType(): string
    {
        return $this->communicationType;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getEmailFrom(): ?string
    {
        return $this->emailFrom;
    }

    public function getEmailTo(): ?string
    {
        return $this->emailTo;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getScheduledDate(): ?string
    {
        return $this->scheduledDate;
    }

    public function getCompletedDate(): ?string
    {
        return $this->completedDate;
    }

    public function getAssignedTo(): ?string
    {
        return $this->assignedTo;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function isFollowUpRequired(): bool
    {
        return $this->followUpRequired;
    }

    public function getFollowUpDate(): ?string
    {
        return $this->followUpDate;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getEmailMessageId(): ?string
    {
        return $this->emailMessageId;
    }

    public function getAttachmentPath(): ?string
    {
        return $this->attachmentPath;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'debtor_no' => $this->debtorNo,
            'contact_id' => $this->contactId,
            'communication_type' => $this->communicationType,
            'direction' => $this->direction,
            'subject' => $this->subject,
            'message' => $this->message,
            'email_from' => $this->emailFrom,
            'email_to' => $this->emailTo,
            'phone_number' => $this->phoneNumber,
            'duration_minutes' => $this->durationMinutes,
            'status' => $this->status,
            'scheduled_date' => $this->scheduledDate,
            'completed_date' => $this->completedDate,
            'assigned_to' => $this->assignedTo,
            'priority' => $this->priority,
            'follow_up_required' => $this->followUpRequired,
            'follow_up_date' => $this->followUpDate,
            'notes' => $this->notes,
            'email_message_id' => $this->emailMessageId,
            'attachment_path' => $this->attachmentPath,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }

    public function update(array $data): void
    {
        if (isset($data['communication_type'])) {
            $this->communicationType = $data['communication_type'];
        }
        if (isset($data['direction'])) {
            $this->direction = $data['direction'];
        }
        if (isset($data['subject'])) {
            $this->subject = $data['subject'];
        }
        if (isset($data['message'])) {
            $this->message = $data['message'];
        }
        if (isset($data['email_from'])) {
            $this->emailFrom = $data['email_from'];
        }
        if (isset($data['email_to'])) {
            $this->emailTo = $data['email_to'];
        }
        if (isset($data['phone_number'])) {
            $this->phoneNumber = $data['phone_number'];
        }
        if (isset($data['duration_minutes'])) {
            $this->durationMinutes = $data['duration_minutes'];
        }
        if (isset($data['status'])) {
            $this->status = $data['status'];
        }
        if (isset($data['scheduled_date'])) {
            $this->scheduledDate = $data['scheduled_date'];
        }
        if (isset($data['completed_date'])) {
            $this->completedDate = $data['completed_date'];
        }
        if (isset($data['assigned_to'])) {
            $this->assignedTo = $data['assigned_to'];
        }
        if (isset($data['priority'])) {
            $this->priority = $data['priority'];
        }
        if (isset($data['follow_up_required'])) {
            $this->followUpRequired = (bool)$data['follow_up_required'];
        }
        if (isset($data['follow_up_date'])) {
            $this->followUpDate = $data['follow_up_date'];
        }
        if (isset($data['notes'])) {
            $this->notes = $data['notes'];
        }
        if (isset($data['email_message_id'])) {
            $this->emailMessageId = $data['email_message_id'];
        }
        if (isset($data['attachment_path'])) {
            $this->attachmentPath = $data['attachment_path'];
        }
        $this->updatedAt = date('Y-m-d H:i:s');
    }
}
