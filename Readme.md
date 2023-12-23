# MultiMail
MultiMail allows users to specify additional email addresses and swap between the secondary and primary email addresses at will.

This gives users the ability to recover their account if their primary email address becomes unavailable.

## Setup
- Add `wfLoadExtension( 'MultiMail' );` to `LocalSettings.php`
- [Configure](#Configuration) where necessary
- Run `update.php`

## Configuration
MultiMail adds one new right, `multimail`, which gives users permission to access Special:EmailAddresses. It is available to everyone by default, though Special:EmailAddresses requires that any user that uses it is logged-in. This is similar to `editmyprivateinfo` for Special:Preferences.

## Usage in Wiki families
MultiMail supports wiki families out of the box. If you use shared tables, simply add `user_secondary_email` to `$wgSharedTables`. If you use a different setup (such as [CentralAuth](https://www.mediawiki.org/wiki/Extension:CentralAuth)), configure `$wgVirtualDomainsMapping` for `virtual-multimail`.

To use a dedicated database named `MultiMail`:
```
$wgVirtualDomainsMapping['virtual-multimail'] = [ 'db' => 'MultiMail' ];
```
This also allows specifying a cluster:
```
$wgVirtualDomainsMapping['virtual-multimail'] = [ 'db' => 'MultiMail', 'cluster' => 'extension1' ];
```

See [the documentation of $wgVirtualDomainsMapping](https://www.mediawiki.org/wiki/Special:MyLanguage/Manual:$wgVirtualDomainsMapping) for more information on how to use it.

## Compatibility with other extensions
MultiMail calls the `PrefsEmailAudit` hook whenever the primary email address changes. Indirectly, the hooks `UserSetEmail` and `UserSetEmailAuthenticationTimestamp` are also called, meaning any extension that registers handlers for these hooks is compatible out of the box.
