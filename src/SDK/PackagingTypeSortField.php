<?php

namespace BayWaReLusy\PackagingTypesAPI\SDK;

enum PackagingTypeSortField: string
{
    case ID     = 'id';
    case NAME   = 'name';
    case CUSTOM = 'sortOrder';
}
