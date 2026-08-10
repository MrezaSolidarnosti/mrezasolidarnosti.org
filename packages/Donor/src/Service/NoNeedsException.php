<?php
namespace Solidarity\Donor\Service;

/**
 * Thrown by Donor::createTransaction() when an on-demand donation allocates nothing.
 *
 * The message is donor-facing and already narrowed to the actual blocker (no needs at
 * all / none in the chosen project / none payable by the chosen payment types / amount
 * too small), so callers should surface getMessage() as-is rather than substituting a
 * generic error.
 */
class NoNeedsException extends \Exception
{
}
