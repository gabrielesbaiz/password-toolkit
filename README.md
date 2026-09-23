<p align="center">
    <img src="art/password-toolkit-logo.png" alt="PasswordToolkit" width="600">
</p>

# PasswordToolkit

Memorable passwords for Laravel — `Fearless-Luke-Skywalker-481902` rather than `xK#9$!qZ` — drawn from 200 curated dictionaries, in the language you choose, with dictionaries of your own alongside them.

[![Latest version](https://img.shields.io/packagist/v/gabrielesbaiz/password-toolkit.svg?style=flat-square)](https://packagist.org/packages/gabrielesbaiz/password-toolkit)
[![PHP](https://img.shields.io/packagist/dependency-v/gabrielesbaiz/password-toolkit/php?style=flat-square)](composer.json)
[![Laravel](https://img.shields.io/packagist/dependency-v/gabrielesbaiz/password-toolkit/illuminate%2Fsupport?style=flat-square&label=laravel)](composer.json)
[![Downloads](https://img.shields.io/packagist/dt/gabrielesbaiz/password-toolkit.svg?style=flat-square)](https://packagist.org/packages/gabrielesbaiz/password-toolkit)
[![Stars](https://img.shields.io/github/stars/gabrielesbaiz/password-toolkit?style=flat-square&logo=github)](https://github.com/gabrielesbaiz/password-toolkit/stargazers)
[![Sponsor](https://img.shields.io/github/sponsors/gabrielesbaiz?style=flat-square&label=sponsor&logo=github)](https://github.com/sponsors/gabrielesbaiz)

### 📖 [Read the documentation →](https://gabrielesbaiz.github.io/password-toolkit/)

Every setting, a builder that writes your config file, a terminal playground
where every command runs against the real dictionaries, and the whole shelf of
200 with filters.

> [!CAUTION]
> **Upgrading from 1.x?** Read [UPGRADE.md](UPGRADE.md) first. `generate($count)`
> is now `generateMany($count)` and `generate()` throws instead of returning
> `null`. Most of it is shimmed; four changes need an edit in your code.

> [!IMPORTANT]
> A ⭐ costs you nothing and helps other developers find this package.
> [Sponsoring](https://github.com/sponsors/gabrielesbaiz) keeps it compatible
> with every new Laravel release.

## What it does

Laravel ships `Str::password()`. If a machine is the only thing that will ever
read the secret, use it — it is higher entropy, shorter, and nobody has to say
it out loud. This package exists for the passwords a person has to handle:

- **201 dictionaries, 4,411 names**, filterable by group, tag and how far the names travel.
- **Two languages, properly.** Italian adjectives agree with the gender of the name, and word order follows the language.
- **Your own dictionaries**, from a directory, inline config or a runtime registration.
- **An honest strength report**, counting what this package could have produced rather than what the alphabet allows.
- **A validation rule**, configurable bands, and eighteen flags on the artisan command.

Leetspeak is credited exactly zero bits, because a deterministic transform adds
no work for an attacker who has read your config.

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12 or 13

## Installation

```bash
composer require gabrielesbaiz/password-toolkit

php artisan vendor:publish --tag=password-toolkit-config
php artisan vendor:publish --tag=password-toolkit-translations

php artisan password-toolkit:generate 5 --report
```

The service provider is auto-discovered and there is nothing to register: no
migrations, no tables, no assets. Both publish steps are optional — the defaults
generate working passwords untouched.

**[Full installation guide →](https://gabrielesbaiz.github.io/password-toolkit/#/install)**

## Artisan commands

| Command | Purpose |
|---|---|
| `password-toolkit:generate {count}` | Generate passwords. `--report` adds score, entropy and crack time. |
| `password-toolkit:generate --list` | Show which dictionaries resolve, with group, reach and tags. |
| `password-toolkit:make-dictionary {key}` | Scaffold a dictionary of your own. |

Eighteen flags in all. See the
[commands page](https://gabrielesbaiz.github.io/password-toolkit/#/commands).

## Documentation

| | |
|---|---|
| [Documentation site](https://gabrielesbaiz.github.io/password-toolkit/) | Everything: install, configure, operate. |
| [Playground](https://gabrielesbaiz.github.io/password-toolkit/#/play) | Every command, against real dictionaries, in your browser. |
| [Configuration builder](https://gabrielesbaiz.github.io/password-toolkit/#/config) | Set what you need; it writes the config file. |
| [All dictionaries](https://gabrielesbaiz.github.io/password-toolkit/#/shelf) | All 201, filterable by group, tag and reach. |
| [UPGRADE.md](UPGRADE.md) | Upgrading from 1.x. Read before you start. |
| [CHANGELOG.md](CHANGELOG.md) | What changed, and when. |

## Testing

```bash
composer test        # Pest
composer analyse     # PHPStan
composer format      # Pint
```

There is no CI. Those three commands are the contract.

## Contributing

Thank you for considering contributing. The guide is in
[CONTRIBUTING.md](CONTRIBUTING.md).

## Security vulnerabilities

Please review [SECURITY.md](SECURITY.md) for reporting a vulnerability. Please
do not open a public issue.

## Credits

Written and maintained by [Gabriele Sbaiz](https://github.com/gabrielesbaiz).

This package builds on Laravel and
[spatie/laravel-package-tools](https://github.com/spatie/laravel-package-tools).

## Support this package

If it is useful to you:

- ⭐ **Star the repo.** Free, thirty seconds, and it is the first signal other developers look at.
- ❤️ **[Become a sponsor](https://github.com/sponsors/gabrielesbaiz).** From $5 a month.
- 🐛 **Open a good issue.** A clear reproduction is worth more than you think.
- 🗣️ **Tell another Laravel developer.** Word of mouth is how packages survive.

[![Sponsor on GitHub](https://img.shields.io/badge/Sponsor-gabrielesbaiz-ff69b4?style=for-the-badge&logo=github-sponsors)](https://github.com/sponsors/gabrielesbaiz)

## Disclaimer

This package is provided **as is**, without warranty of any kind, express or
implied, including but not limited to the warranties of merchantability,
fitness for a particular purpose, title and non-infringement. To the fullest
extent permitted by applicable law, in no event shall the authors, copyright
holders or contributors be liable for any claim, damages or other liability —
whether in an action of contract, tort or otherwise — arising from, out of or in
connection with this package or its use, including without limitation any
direct, indirect, incidental, special, exemplary, consequential or punitive
damages, loss of data, loss of profits, business interruption, account
compromise, or unauthorised access.

This package generates passwords that are deliberately weaker than random ones
in exchange for being memorable, and it reports that weakness honestly. Whoever
deploys it is responsible for deciding whether that trade is acceptable. That
responsibility includes, and is not limited to, choosing appropriate settings,
reading the reported entropy before relying on a password, forcing a change
after first use, hashing what you store, meeting whatever regulatory or
contractual obligations apply to you, and reviewing the code yourself before
putting it in front of an account you cannot afford to lose. Nothing here
constitutes security, legal or compliance advice.

Use of this package is entirely at your own risk.

## License

MIT. See [LICENSE.md](LICENSE.md). The MIT licence's warranty disclaimer and
limitation of liability apply in full, alongside the disclaimer above.
