<?php

namespace App\Enums;

/**
 * EPP domain statuses (RFC 5731) with their RDAP mapping (RFC 8056).
 */
enum DomainStatus: string
{
    case ClientDeleteProhibited = 'clientDeleteProhibited';
    case ClientHold = 'clientHold';
    case ClientRenewProhibited = 'clientRenewProhibited';
    case ClientTransferProhibited = 'clientTransferProhibited';
    case ClientUpdateProhibited = 'clientUpdateProhibited';
    case ServerDeleteProhibited = 'serverDeleteProhibited';
    case ServerHold = 'serverHold';
    case ServerRenewProhibited = 'serverRenewProhibited';
    case ServerTransferProhibited = 'serverTransferProhibited';
    case ServerUpdateProhibited = 'serverUpdateProhibited';
    case PendingCreate = 'pendingCreate';
    case PendingDelete = 'pendingDelete';
    case PendingRenew = 'pendingRenew';
    case PendingTransfer = 'pendingTransfer';
    case PendingUpdate = 'pendingUpdate';
    case Inactive = 'inactive';

    /**
     * RDAP status value as registered in the IANA "RDAP JSON Values" registry.
     */
    public function rdap(): string
    {
        return strtolower(trim(preg_replace('/([A-Z])/', ' $1', $this->value)));
    }

    /**
     * Whether the status removes the delegation from the published zone.
     */
    public function removesFromZone(): bool
    {
        return in_array($this, [self::ClientHold, self::ServerHold, self::Inactive], true);
    }
}
