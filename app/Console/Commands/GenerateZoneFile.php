<?php

namespace App\Console\Commands;

use App\Models\Tld;
use App\Services\ZoneFileGenerator;
use App\Support\Idn;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[Signature('tinytld:zone {tld : TLD name, Unicode or A-label} {--output= : Write the zone to this file instead of stdout}')]
#[Description('Generate the DNS zone file of a TLD and bump its SOA serial')]
class GenerateZoneFile extends Command
{
    /**
     * @var array<int, string>
     */
    protected $aliases = ['registry:zone'];

    public function handle(ZoneFileGenerator $generator): int
    {
        $name = (string) $this->argument('tld');
        $tld = Idn::isValidHostName($name) ? Tld::query()->whereHostName($name)->first() : null;

        if ($tld === null) {
            $this->error("Unknown TLD [{$name}].");

            return self::FAILURE;
        }

        $serial = $generator->bumpSerial($tld);
        $zone = $generator->generate($tld);

        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            if (file_put_contents($output, $zone) === false) {
                $this->error("Unable to write [{$output}].");

                return self::FAILURE;
            }

            $this->info("Zone {$tld->name}. written to {$output} (serial {$serial}).");

            return self::SUCCESS;
        }

        $this->output->write($zone, false, OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }
}
