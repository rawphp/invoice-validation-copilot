<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The service taxonomy a supplier invoice is classified into during extraction.
 *
 * Backed by stable string values (used as the tool-schema enum and persisted /
 * compared downstream). The {@see self::label()} provides the human-facing AU
 * taxonomy wording shown in the UI and explanation step.
 *
 * Unknown / unclassifiable invoices (and non-invoice documents) map to {@see self::Other}.
 */
enum ServiceCategory: string
{
    case ConstructionTrades = 'construction_trades';
    case ProfessionalServices = 'professional_services';
    case ItSoftware = 'it_software';
    case CleaningMaintenance = 'cleaning_maintenance';
    case LogisticsFreight = 'logistics_freight';
    case Utilities = 'utilities';
    case MarketingMedia = 'marketing_media';
    case EquipmentSupplies = 'equipment_supplies';
    case Other = 'other';

    /**
     * Human-facing label for this category.
     */
    public function label(): string
    {
        return match ($this) {
            self::ConstructionTrades => 'Construction & Trades',
            self::ProfessionalServices => 'Professional Services',
            self::ItSoftware => 'IT & Software',
            self::CleaningMaintenance => 'Cleaning & Maintenance',
            self::LogisticsFreight => 'Logistics & Freight',
            self::Utilities => 'Utilities',
            self::MarketingMedia => 'Marketing & Media',
            self::EquipmentSupplies => 'Equipment & Supplies',
            self::Other => 'Other',
        };
    }

    /**
     * Resolve a raw model value to a case, defaulting to {@see self::Other} when
     * the value is missing or not a recognised category. The model is forced
     * toward the enum via the tool schema, but extraction never throws on a
     * stray value — it degrades to Other.
     */
    public static function fromModel(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value)) {
            return self::tryFrom($value) ?? self::Other;
        }

        return self::Other;
    }
}
