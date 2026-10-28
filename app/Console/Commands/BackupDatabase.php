<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature   = 'backup:database {--keep=14 : Days of local backups to keep} {--no-mail : Skip emailing the superusers}';
    protected $description = 'Dump the Postgres database, keep recent copies on disk and email the latest to the superusers';

    /** Resend rejects messages over ~40MB; stay well under it. */
    private const MAX_ATTACHMENT_BYTES = 25 * 1024 * 1024;

    public function handle(): int
    {
        $db = config('database.connections.' . config('database.default'));
        if (($db['driver'] ?? null) !== 'pgsql') {
            $this->error('Only Postgres databases are supported.');
            return self::FAILURE;
        }

        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $file = sprintf('%s/%s-%s.sql.gz', $dir, $db['database'], now()->format('Ymd-His'));
        // bash (not sh) so PIPESTATUS reports a pg_dump failure instead of gzip's exit code.
        $process = Process::fromShellCommandline(
            'bash -c ' . escapeshellarg('pg_dump --no-owner -h "$H" -p "$P" -U "$U" "$D" | gzip > "$F"; exit ${PIPESTATUS[0]}'),
            null,
            ['H' => $db['host'], 'P' => $db['port'], 'U' => $db['username'], 'D' => $db['database'], 'F' => $file, 'PGPASSWORD' => $db['password']],
        );
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful() || !is_file($file) || filesize($file) < 200) {
            @unlink($file);
            $this->error('pg_dump failed: ' . trim($process->getErrorOutput()));
            return self::FAILURE;
        }
        chmod($file, 0600);

        $size = filesize($file);
        $this->info(sprintf('Backup written: %s (%s KB)', basename($file), number_format($size / 1024, 1)));

        foreach (glob($dir . '/*.sql.gz') as $old) {
            if (filemtime($old) < now()->subDays((int) $this->option('keep'))->getTimestamp()) {
                unlink($old);
            }
        }

        if ($this->option('no-mail')) {
            return self::SUCCESS;
        }

        $emails = User::where('role', 'superuser')->whereNotNull('email')->pluck('email')->all();
        if (!$emails) {
            $this->warn('No superuser email found; backup kept on disk only.');
            return self::SUCCESS;
        }

        $tooBig = $size > self::MAX_ATTACHMENT_BYTES;
        $body = "Daily database backup: " . basename($file) . ' (' . number_format($size / 1024, 1) . " KB).\n\n"
            . ($tooBig
                ? "The file is too large to attach. It is stored on the server in storage/app/backups.\n"
                : "The backup is attached (gzip-compressed SQL). It contains all system data; keep it private.\n");

        Mail::raw($body, function ($m) use ($emails, $file, $tooBig) {
            $m->to($emails)->subject('Database backup ' . now()->format('Y-m-d'));
            if (!$tooBig) {
                $m->attach($file);
            }
        });
        $this->info('Emailed to ' . implode(', ', $emails));

        return self::SUCCESS;
    }
}
