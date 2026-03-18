<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Resources;

use QrCommunication\VivaMerchant\HttpClient;

/**
 * Bank Account & Transfer operations.
 *
 * Link IBANs, check transfer options, execute SEPA transfers.
 * All endpoints use Bearer OAuth on the New API.
 *
 * Prerequisite: "Allow transfers between accounts" + "Account Transactions Credentials"
 * must be configured in Settings > API Access.
 *
 * @see https://developer.viva.com/apis-for-payments/bank-transfer-api/
 */
final class BankAccounts
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Link a bank account (IBAN validation + registration).
     *
     * @param  string  $iban  IBAN of the bank account
     * @param  string  $beneficiaryName  Name of the account holder
     * @param  string|null  $friendlyName  Optional display name
     * @return array{bankAccountId: string, isVivaIban: bool}
     */
    public function link(string $iban, string $beneficiaryName, ?string $friendlyName = null): array
    {
        return $this->http->post('/banktransfers/v1/bankaccounts', array_filter([
            'iban' => $iban,
            'beneficiaryName' => $beneficiaryName,
            'friendlyName' => $friendlyName,
        ]));
    }

    /**
     * Retrieve bank transfer options (instant SEPA, cost options).
     *
     * Only for external IBANs — not for Viva-internal wallets.
     *
     * @return array<string, mixed>  Available instruction types (SHA, OUR, instant)
     */
    public function transferOptions(string $bankAccountId): array
    {
        return $this->http->get("/banktransfers/v1/bankaccounts/{$bankAccountId}/instructiontypes");
    }

    /**
     * Create a bank transfer fee command (check fees before transferring).
     *
     * @param  string  $bankAccountId  Linked bank account UUID
     * @param  int  $amount  Amount in cents
     * @param  string  $walletId  Source wallet UUID
     * @param  bool  $isInstant  Instant SEPA transfer
     * @param  string  $instructionType  'SHA' (shared) or 'OUR' (ours)
     * @return array{bankCommandId: string, fee: int}
     */
    public function feeCommand(string $bankAccountId, int $amount, string $walletId, bool $isInstant = false, string $instructionType = 'SHA'): array
    {
        return $this->http->post("/banktransfers/v1/bankaccounts/{$bankAccountId}/fees", [
            'amount' => $amount,
            'walletId' => $walletId,
            'isInstant' => $isInstant,
            'instructionType' => $instructionType,
        ]);
    }

    /**
     * Execute a bank transfer (SEPA).
     *
     * @param  string  $bankAccountId  Linked bank account UUID
     * @param  int  $amount  Amount in cents
     * @param  string  $walletId  Source wallet UUID
     * @param  string|null  $bankCommandId  Fee command ID (optional, determines transfer options)
     * @param  string|null  $description  Transfer description
     * @return array{commandId: string, isInstant: bool, fee: int}
     */
    public function send(string $bankAccountId, int $amount, string $walletId, ?string $bankCommandId = null, ?string $description = null): array
    {
        return $this->http->post("/banktransfers/v1/bankaccounts/{$bankAccountId}:send", array_filter([
            'amount' => $amount,
            'walletId' => $walletId,
            'bankCommandId' => $bankCommandId,
            'description' => $description,
        ]));
    }

    /**
     * Retrieve all linked bank accounts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        return $this->http->get('/banktransfers/v1/bankaccounts');
    }

    /**
     * Retrieve a linked bank account by ID.
     *
     * @return array<string, mixed>
     */
    public function get(string $bankAccountId): array
    {
        return $this->http->get("/banktransfers/v1/bankaccounts/{$bankAccountId}");
    }
}
