<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Restricted specialization list that replaces the free-text `specialty`
 * for donor reporting. The legacy free-text column stays untouched.
 */
enum Specialization: string
{
    use HasValues;

    case ComputerScience = 'computer_science';
    case SoftwareEngineering = 'software_engineering';
    case InformationSystems = 'information_systems';
    case Cybersecurity = 'cybersecurity';
    case DataScience = 'data_science';
    case CivilEngineering = 'civil_engineering';
    case ArchitecturalEngineering = 'architectural_engineering';
    case ElectricalEngineering = 'electrical_engineering';
    case MechanicalEngineering = 'mechanical_engineering';
    case ChemicalEngineering = 'chemical_engineering';
    case Medicine = 'medicine';
    case Nursing = 'nursing';
    case Pharmacy = 'pharmacy';
    case Dentistry = 'dentistry';
    case MedicalLaboratory = 'medical_laboratory';
    case BusinessAdministration = 'business_administration';
    case Accounting = 'accounting';
    case Economics = 'economics';
    case Finance = 'finance';
    case Marketing = 'marketing';
    case Law = 'law';
    case Education = 'education';
    case Psychology = 'psychology';
    case SocialWork = 'social_work';
    case ArabicLanguage = 'arabic_language';
    case EnglishLanguage = 'english_language';
    case Media = 'media';
    case GraphicDesign = 'graphic_design';
    case Agriculture = 'agriculture';
    case Sciences = 'sciences';
    case Mathematics = 'mathematics';
    case Sharia = 'sharia';
    case PoliticalScience = 'political_science';
    case Tourism = 'tourism';
    case Other = 'other';
}
