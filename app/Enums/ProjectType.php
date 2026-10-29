<?php

namespace App\Enums;

enum ProjectType: string
{
    case Villa = 'villa';
    case ApartmentBuilding = 'apartment_building';
    case Renovation = 'renovation';
    case Commercial = 'commercial';
    case Industrial = 'industrial';
    case InteriorDesign = 'interior_design';
    case UrbanPlanning = 'urban_planning';
    case Other = 'other';
}
