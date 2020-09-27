<?php

namespace App\Commands;

use Stecman\Component\Symfony\Console\BashCompletion\Completion;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionCommand as CoreCompletionCommandAlias;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionHandler;

class CompletionCommand extends CoreCompletionCommandAlias
{
    protected function configureCompletion(CompletionHandler $handler)
    {
        parent::configureCompletion($handler);
//        $handler->addHandler(
//            new Completion(
//                'dl',
//                'client',
//                Completion::TYPE_ARGUMENT,
//                function () use ($handler) {
//                    /** @var \App\Commands\ImportCommand $dl */
//                    $dl = $this->getApplication()->get('dl');
//                    return $dl->autocompleteClient($handler->getContext());
//                }
//            )
//        );
//        $handler->addHandler(
//            new Completion(
//                'dl',
//                'server',
//                Completion::TYPE_ARGUMENT,
//                function () use ($handler) {
//                    /** @var \App\Commands\ImportCommand $dl */
//                    $dl = $this->getApplication()->get('dl');
//                    return $dl->autoCompleteServers($handler->getContext());
//                }
//            )
//        );
//        $handler->addHandler(
//            new Completion(
//                'dl',
//                'database',
//                Completion::TYPE_ARGUMENT,
//                function () use ($handler) {
//                    /** @var \App\Commands\ImportCommand $dl */
//                    $dl = $this->getApplication()->get('dl');
//                    return $dl->autoCompleteDatabases($handler->getContext());
//                }
//            )
//        );
    }
}
