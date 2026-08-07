<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * Creates the seven operator accounts on a fresh installation.
 *
 * Passwords are GENERATED, not hardcoded. This seeder previously shipped
 * a fixed password derived from each username -- a guessable pattern, so
 * knowing any one username gave you that account. Those had never been
 * rotated on the live system and were still valid at audit time (AUTH-01,
 * Critical). Baking them into the seeder meant every fresh deployment
 * recreated the same hole, and anyone reading this file in the repository
 * knew every password.
 *
 * Each account now gets a random passphrase, printed ONCE to the console for
 * the operator to record and distribute. Nothing is written to disk in
 * plaintext and nothing recoverable is left in the repository -- if the
 * output is lost, reset the account from Settings > Users rather than
 * re-running this.
 *
 * Existing accounts are still skipped, so re-running this on a populated
 * database cannot reset anyone's password.
 */
class UserSeeder extends Seeder
{
    /** Syllable parts -- these produce passphrases staff can read off a sheet and type. */
    private const CONSONANTS = ['b', 'd', 'f', 'g', 'k', 'l', 'm', 'n', 'p', 'r', 's', 't', 'v', 'z', 'ch', 'sh', 'th', 'br', 'tr', 'st'];
    private const VOWELS     = ['a', 'e', 'i', 'o', 'u', 'ai', 'ou', 'ea'];

    public function run()
    {
        $accounts = [
            ['username' => 'admin',     'role' => 'admin', 'full_name' => 'System Administrator'],
            ['username' => 'esection1', 'role' => 'staff', 'full_name' => 'E-Section Staff Desk 1'],
            ['username' => 'esection2', 'role' => 'staff', 'full_name' => 'E-Section Staff Desk 2'],
            ['username' => 'esection3', 'role' => 'staff', 'full_name' => 'E-Section Staff Desk 3'],
            ['username' => 'esection4', 'role' => 'staff', 'full_name' => 'E-Section Staff Desk 4'],
            ['username' => 'esection5', 'role' => 'staff', 'full_name' => 'E-Section Staff Desk 5'],
            ['username' => 'esection6', 'role' => 'staff', 'full_name' => 'E-Section Staff Desk 6'],
        ];

        $created = [];

        foreach ($accounts as $account) {
            $existing = $this->db->table('users')
                                 ->where('username', $account['username'])
                                 ->get()->getRow();

            // Never touch an account that already exists -- re-running this
            // seeder must not reset a live password.
            if ($existing) {
                continue;
            }

            $password = $this->generatePassword();
            $now      = date('Y-m-d H:i:s');

            $this->db->table('users')->insert([
                'username'      => $account['username'],
                'email'         => $account['username'] . '@idol.mu.ac.in',
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'role'          => $account['role'],
                'full_name'     => $account['full_name'],
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            $created[$account['username']] = $password;
        }

        $this->report($created);
    }

    /**
     * A ~18-character passphrase: two pronounceable words around a number and
     * a symbol, e.g. "Karobi-4821#Tuve". Long enough to be strong, regular
     * enough to be typed correctly from a printed sheet on the first attempt
     * -- which matters more than raw entropy for an office that will
     * otherwise write a shorter one on a sticky note.
     */
    private function generatePassword(): string
    {
        return ucfirst($this->syllables(3))
             . '-' . random_int(1000, 9999)
             . ['#', '@', '$', '%', '&'][random_int(0, 4)]
             . ucfirst($this->syllables(2));
    }

    private function syllables(int $count): string
    {
        $out = '';

        for ($i = 0; $i < $count; $i++) {
            $out .= self::CONSONANTS[random_int(0, count(self::CONSONANTS) - 1)]
                  . self::VOWELS[random_int(0, count(self::VOWELS) - 1)];
        }

        return $out;
    }

    /**
     * Printed to the console only. Deliberately not written to a file: a
     * plaintext credential file left in the project root is exactly the
     * finding (SEC-05) this audit removed.
     *
     * @param array<string, string> $created
     */
    private function report(array $created): void
    {
        if ($created === []) {
            CLI::write('UserSeeder: all seven accounts already exist -- no passwords changed.', 'yellow');

            return;
        }

        CLI::newLine();
        CLI::write('=== RECORD THESE NOW -- they are not stored anywhere and cannot be recovered ===', 'red');
        CLI::newLine();

        foreach ($created as $username => $password) {
            CLI::write(sprintf('  %-12s %s', $username, $password), 'green');
        }

        CLI::newLine();
        CLI::write('Distribute the staff passwords, then have each user change theirs.', 'yellow');
        CLI::newLine();
    }
}
