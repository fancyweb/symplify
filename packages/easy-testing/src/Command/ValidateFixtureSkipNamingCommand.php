<?php

declare(strict_types=1);

namespace Symplify\EasyTesting\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symplify\EasyTesting\Finder\FixtureFinder;
use Symplify\EasyTesting\MissplacedSkipPrefixResolver;
use Symplify\EasyTesting\ValueObject\Option;
use Symplify\PackageBuilder\Console\Command\AbstractSymplifyCommand;

final class ValidateFixtureSkipNamingCommand extends AbstractSymplifyCommand
{
    public function __construct(
        private MissplacedSkipPrefixResolver $missplacedSkipPrefixResolver,
        private FixtureFinder $fixtureFinder
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('validate-fixture-skip-naming');
        $this->addArgument(Option::SOURCE, InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Paths to analyse');
        $this->setDescription('Check that skipped fixture files (without `-----` separator) have a "skip" prefix');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = (array) $input->getArgument(Option::SOURCE);
        $fixtureFileInfos = $this->fixtureFinder->find($source);

        $missplacedFixtureFileInfos = $this->missplacedSkipPrefixResolver->resolve($fixtureFileInfos);

        if ($missplacedFixtureFileInfos === []) {
            $message = sprintf('All %d fixture files have valid names', count($fixtureFileInfos));
            $this->symfonyStyle->success($message);
            return self::SUCCESS;
        }

        foreach ($missplacedFixtureFileInfos['incorrect_skips'] as $missplacedFixtureFileInfo) {
            $errorMessage = sprintf(
                'The file "%s" should drop the "skip/keep" prefix',
                $missplacedFixtureFileInfo->getRelativeFilePathFromCwd()
            );
            $this->symfonyStyle->note($errorMessage);
        }

        foreach ($missplacedFixtureFileInfos['missing_skips'] as $missplacedFixtureFileInfo) {
            $errorMessage = sprintf(
                'The file "%s" should start with "skip/keep" prefix',
                $missplacedFixtureFileInfo->getRelativeFilePathFromCwd()
            );
            $this->symfonyStyle->note($errorMessage);
        }

        $countError = count($missplacedFixtureFileInfos['incorrect_skips']) + count(
            $missplacedFixtureFileInfos['missing_skips']
        );
        if ($countError === 0) {
            $message = sprintf('All %d fixture files have valid names', count($fixtureFileInfos));
            $this->symfonyStyle->success($message);
            return self::SUCCESS;
        }

        $errorMessage = sprintf('Found %d test file fixtures with wrong prefix', $countError);
        $this->symfonyStyle->error($errorMessage);

        return self::FAILURE;
    }
}
