<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Cash = 'cash';
    case BillOfExchange = 'bill_of_exchange';
    case Other = 'other';
}
