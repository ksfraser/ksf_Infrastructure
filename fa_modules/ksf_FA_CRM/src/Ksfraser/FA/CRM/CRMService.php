<?php
/**
 * FrontAccounting CRM Adapter Service
 *
 * CRM service layer for the FA adapter.
 *
 * @package Ksfraser\FA\CRM
 * @version 1.0.0
 */

namespace Ksfraser\FA\CRM;

use Ksfraser\Exceptions\CRM\{CRMException, CRMCustomerNotFoundException, CRMCustomerAlreadyExistsException, CRMCustomerValidationException, CRMContactNotFoundException, CRMContactValidationException, CRMOpportunityNotFoundException, CRMOpportunityValidationException, CRMOpportunityStatusTransitionException, CRMCommunicationNotFoundException, CRMCommunicationValidationException, CRMCommunicationStatusException, CRMDatabaseException, CRMPermissionException, CRMConfigurationException, CRMIntegrationException};

/**
 * CRM Service
 *
 * Handles customer relationship management, contacts, opportunities, and analytics
 */
class CRMService
{
    /**
     * Create a CRM customer profile
     *
     * @param array $customerData Customer profile data
     * @return CRMCustomer The created CRM customer
     * @throws CRMException
     */
    public function createCRMCustomer(array $customerData): CRMCustomer
    {
        $this->validateCRMCustomerData($customerData);

        $existing = $this->dbFetch(
            'SELECT id FROM crm_customers WHERE debtor_no = ?',
            [$customerData['debtor_no']]
        );

        if ($existing) {
            throw new CRMException("CRM profile already exists for customer {$customerData['debtor_no']}");
        }

        $customerData['customer_since'] = $customerData['customer_since'] ?? date('Y-m-d');
        $customerData['edi_enabled'] = $customerData['edi_enabled'] ?? false;
        $customerData['marketing_opt_out'] = $customerData['marketing_opt_out'] ?? false;
        $customerData['preferred_contact_method'] = $customerData['preferred_contact_method'] ?? 'email';
        $customerData['credit_rating'] = $customerData['credit_rating'] ?? 'good';
        $customerData['payment_reliability'] = $customerData['payment_reliability'] ?? 100.00;
        $customerData['created_at'] = date('Y-m-d H:i:s');
        $customerData['updated_at'] = date('Y-m-d H:i:s');

        try {
            $this->dbInsert('crm_customers', $customerData);
            $customer = new CRMCustomer($customerData);
            return $customer;
        } catch (\Exception $e) {
            throw new CRMException('Failed to create CRM customer profile: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get CRM customer profile
     *
     * @param string $debtorNo Customer debtor number
     * @return CRMCustomer The CRM customer profile
     * @throws CRMCustomerNotFoundException
     */
    public function getCRMCustomer(string $debtorNo): CRMCustomer
    {
        $data = $this->dbFetch(
            'SELECT * FROM crm_customers WHERE debtor_no = ?',
            [$debtorNo]
        );

        if (!$data) {
            throw new CRMCustomerNotFoundException($debtorNo);
        }

        return new CRMCustomer($data);
    }

    /**
     * Create a customer contact
     *
     * @param array $contactData Contact data
     * @return CRMContact The created contact
     * @throws CRMException
     */
    public function createContact(array $contactData): CRMContact
    {
        $this->validateContactData($contactData);

        $contactData['is_primary'] = $contactData['is_primary'] ?? false;
        $contactData['inactive'] = $contactData['inactive'] ?? false;
        $contactData['created_at'] = date('Y-m-d H:i:s');
        $contactData['updated_at'] = date('Y-m-d H:i:s');

        try {
            $this->dbInsert('crm_contacts', $contactData);
            $contact = new CRMContact($contactData);
            return $contact;
        } catch (\Exception $e) {
            throw new CRMException('Failed to create customer contact: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get customer contacts
     *
     * @param string $debtorNo Customer debtor number
     * @param bool $activeOnly Return only active contacts
     * @return CRMContact[] Array of customer contacts
     */
    public function getCustomerContacts(string $debtorNo, bool $activeOnly = true): array
    {
        $query = 'SELECT * FROM crm_contacts WHERE debtor_no = ?';
        $params = [$debtorNo];

        if ($activeOnly) {
            $query .= ' AND inactive = 0';
        }

        $query .= ' ORDER BY is_primary DESC, last_name, first_name';

        $rows = $this->dbFetchAll($query, $params);

        return array_map(fn($row) => new CRMContact($row), $rows);
    }

    /**
     * Create a sales opportunity
     *
     * @param array $opportunityData Opportunity data
     * @return CRMOpportunity The created opportunity
     * @throws CRMException
     */
    public function createOpportunity(array $opportunityData): CRMOpportunity
    {
        $this->validateOpportunityData($opportunityData);

        if (!isset($opportunityData['id'])) {
            $opportunityData['id'] = $this->generateOpportunityId();
        }

        $opportunityData['status'] = $opportunityData['status'] ?? 'prospect';
        $opportunityData['probability'] = $opportunityData['probability'] ?? 0.00;
        $opportunityData['created_at'] = date('Y-m-d H:i:s');
        $opportunityData['updated_at'] = date('Y-m-d H:i:s');

        try {
            $this->dbInsert('crm_opportunities', $opportunityData);
            $opportunity = new CRMOpportunity($opportunityData);
            return $opportunity;
        } catch (\Exception $e) {
            throw new CRMException('Failed to create sales opportunity: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get opportunity by ID
     *
     * @param int $opportunityId Opportunity ID
     * @return CRMOpportunity The opportunity
     * @throws CRMOpportunityNotFoundException
     */
    public function getOpportunity(int $opportunityId): CRMOpportunity
    {
        $data = $this->dbFetch(
            'SELECT * FROM crm_opportunities WHERE id = ?',
            [$opportunityId]
        );

        if (!$data) {
            throw new CRMOpportunityNotFoundException($opportunityId);
        }

        return new CRMOpportunity($data);
    }

    /**
     * Record customer communication
     *
     * @param array $communicationData Communication data
     * @return CRMCommunication The recorded communication
     * @throws CRMException
     */
    public function recordCommunication(array $communicationData): CRMCommunication
    {
        $this->validateCommunicationData($communicationData);

        $communicationData['direction'] = $communicationData['direction'] ?? 'outbound';
        $communicationData['status'] = $communicationData['status'] ?? 'completed';
        $communicationData['priority'] = $communicationData['priority'] ?? 'medium';
        $communicationData['follow_up_required'] = $communicationData['follow_up_required'] ?? false;
        $communicationData['completed_date'] = $communicationData['completed_date'] ?? date('Y-m-d H:i:s');
        $communicationData['created_at'] = date('Y-m-d H:i:s');
        $communicationData['updated_at'] = date('Y-m-d H:i:s');

        try {
            $this->dbInsert('crm_communications', $communicationData);
            $communication = new CRMCommunication($communicationData);
            return $communication;
        } catch (\Exception $e) {
            throw new CRMException('Failed to record customer communication: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get customer communications
     *
     * @param string $debtorNo Customer debtor number
     * @param array $filters Additional filters
     * @return CRMCommunication[] Array of communications
     */
    public function getCustomerCommunications(string $debtorNo, array $filters = []): array
    {
        $query = 'SELECT * FROM crm_communications WHERE debtor_no = ?';
        $params = [$debtorNo];

        if (isset($filters['type'])) {
            $query .= ' AND communication_type = ?';
            $params[] = $filters['type'];
        }

        if (isset($filters['from_date'])) {
            $query .= ' AND completed_date >= ?';
            $params[] = $filters['from_date'];
        }

        if (isset($filters['to_date'])) {
            $query .= ' AND completed_date <= ?';
            $params[] = $filters['to_date'];
        }

        $query .= ' ORDER BY completed_date DESC';

        $rows = $this->dbFetchAll($query, $params);

        return array_map(fn($row) => new CRMCommunication($row), $rows);
    }

    /**
     * Validate CRM customer data
     *
     * @param array $data Customer data
     * @throws CRMException
     */
    private function validateCRMCustomerData(array $data): void
    {
        $required = ['debtor_no'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new CRMException("Required field '{$field}' is missing");
            }
        }

        $validContactMethods = ['email', 'phone', 'mail'];
        if (isset($data['preferred_contact_method']) && !in_array($data['preferred_contact_method'], $validContactMethods)) {
            throw new CRMException('Invalid preferred contact method');
        }

        $validRatings = ['excellent', 'good', 'fair', 'poor'];
        if (isset($data['credit_rating']) && !in_array($data['credit_rating'], $validRatings)) {
            throw new CRMException('Invalid credit rating');
        }
    }

    /**
     * Validate contact data
     *
     * @param array $data Contact data
     * @throws CRMException
     */
    private function validateContactData(array $data): void
    {
        $required = ['debtor_no', 'first_name', 'last_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new CRMException("Required field '{$field}' is missing");
            }
        }
    }

    /**
     * Validate opportunity data
     *
     * @param array $data Opportunity data
     * @throws CRMException
     */
    private function validateOpportunityData(array $data): void
    {
        $required = ['debtor_no', 'opportunity_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new CRMException("Required field '{$field}' is missing");
            }
        }

        $validStatuses = ['prospect', 'qualified', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];
        if (isset($data['status']) && !in_array($data['status'], $validStatuses)) {
            throw new CRMException('Invalid opportunity status');
        }
    }

    /**
     * Validate communication data
     *
     * @param array $data Communication data
     * @throws CRMException
     */
    private function validateCommunicationData(array $data): void
    {
        $required = ['debtor_no', 'communication_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new CRMException("Required field '{$field}' is missing");
            }
        }

        $validTypes = ['call', 'meeting', 'email', 'sms', 'note', 'letter'];
        if (!in_array($data['communication_type'], $validTypes)) {
            throw new CRMException('Invalid communication type');
        }

        $validDirections = ['inbound', 'outbound', 'internal'];
        if (isset($data['direction']) && !in_array($data['direction'], $validDirections)) {
            throw new CRMException('Invalid communication direction');
        }
    }

    private function generateOpportunityId(): int
    {
        return (int)$this->dbFetchOne('SELECT COALESCE(MAX(id), 0) + 1 FROM crm_opportunities');
    }

    private function dbFetch(string $sql, array $params = []): ?array
    {
        global $db;
        $result = db_query($this->prepareSql($sql, $params));
        return db_fetch($result) ?: null;
    }

    private function dbFetchAll(string $sql, array $params = []): array
    {
        $result = db_query($this->prepareSql($sql, $params));
        $rows = [];
        while ($row = db_fetch($result)) {
            $rows[] = $row;
        }
        return $rows;
    }

    private function dbFetchOne(string $sql, array $params = [])
    {
        $result = db_query($this->prepareSql($sql, $params));
        $row = db_fetch_row($result);
        return $row ? $row[0] : null;
    }

    private function dbInsert(string $table, array $data): void
    {
        $cols = implode(', ', array_keys($data));
        $vals = implode(', ', array_map(function($v) {
            return db_escape($v);
        }, array_values($data)));
        $sql = "INSERT INTO " . TB_PREF . $table . " ($cols) VALUES ($vals)";
        db_query($sql);
    }

    private function prepareSql(string $sql, array $params): string
    {
        if (preg_match_all('/\?/', $sql, $matches)) {
            $parts = explode('?', $sql);
            $result = array_shift($parts);
            foreach ($parts as $i => $part) {
                $result .= db_escape($params[$i] ?? '') . $part;
            }
            return $result;
        }
        return $sql;
    }
}
