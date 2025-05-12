<?php

namespace BayWaReLusy\PackagingTypesAPI\SDK\PackagingTypeEntity;

enum Category: string
{
    case PARCEL  = 'parcel';
    case PALLET  = 'pallet';
    case SHELTER = 'shelter';
}
