<?php

namespace Sumac\Console\Command\Sync;

use Sumac\Config\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Redmine;

class RemoveDuplicatesCommand extends Command {

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var Redmine\Client
     */
    private $redmineClient;

    /**
     * @var Config
     */
    private $config;

    protected function configure() {
        $this->setName('sync:remove-duplicates')
            ->setDescription('Purge duplicate time entries from Redmine.')
        ->setDefinition([
            new InputArgument(
                'IDs',
                1,
                'A JSON encoded list of Harvest IDs as keys with the Redmine IDs as values.'
            )
        ]);
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        try {
            $this->config = new Config();
        }
        catch (\Exception $exception) {
            $this->io->error($exception->getMessage());
            return false;
        }
        $this->redmineClient = new Redmine\Client($this->config->getRedmineUrl(), $this->config->getRedmineApiKey());
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Sort the array and show the user what they're about to do.
        $time_entries = json_decode($input->getArgument('IDs'), true);
        $time_entries = $this->sortTimeEntries($time_entries);
        $this->purgeTimeEntries($time_entries);
    }

    protected function purgeTimeEntries(array $time_entries) {
        foreach ($time_entries as $harvest_id => $redmine_ids) {
            foreach ($redmine_ids as $redmine_id) {
                // TODO Handle errors here.
                $result = $this->redmineClient->time_entry->remove($redmine_id);
            }
            $this->io->success(sprintf('Removed Redmine time entry IDs %s for Harvest ID %d', implode(',', $redmine_ids), $harvest_id));
        }
    }

    protected function sortTimeEntries($time_entries) {
        foreach ($time_entries as $harvest_id => &$redmine_ids) {
            sort($redmine_ids, SORT_NUMERIC);
            // If more than one duplicate, keep the most recent Redmine time entry and remove the oldest ones.
            if (count($redmine_ids) > 1) {
                array_pop($redmine_ids);
            }
        }
        return $time_entries;
    }

}