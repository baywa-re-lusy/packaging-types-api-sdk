<?php

namespace BayWaReLusy\PackagingTypesAPI\Test;

use Psr\Http\Client\ClientExceptionInterface;

class ClientException extends \Exception implements ClientExceptionInterface
{
}
