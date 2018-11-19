# Cohesion Drupal Archive

Since drush 9.x does not support `drush archive-dump` and `drush archive-restore`, we decided to create this little tool which does pretty much the same thing in a more primitive way.

There are 2 commands: `drupal-archive cda:dump && drupal-archive cda:restore` with have optional parameters `--use-drush` in case you have drush <= 8.1.17 and you want to use the native drush commands.

## Installing

You will need composer installed with php >= 7.1 and `mysql` client tools (mysqladmin) on your machine.

It is best to install the package globally with composer:

```
composer global update cohesiondx8/drupal-archive
```

## Usage

### Archiving a drupal website

```
drupal-client cda:dump <source> <destination> [--overwrite] [--use-drush]
```

- `source` is your drupal website docroot (usually `/var/www/html`).
- `destination` is the created target archive location.
- `--overwrite` is whether you want to overwritte your archive.
- `--use-drush` if you have drush <= 8.1.17 installed then you can use this parameter to call `drush archive-dump` internally.

Example:

```
drupal-client cda:dump /var/www/html/web /tmp/backup.tar --overwrite --use-drush=false -vvv
```

### Restoring a drupal website


```
drupal-client cda:restore <source> <destination> [--db-url=mysql_url] [--overwrite] [--use-drush]
```

- `source` is the archive previously created with `cda:dump`
- `destination` is the target directory where your drupal website will be extracted.
- `--overwrite` is whether you want to overwritte your archive.
- `--use-drush` if you have drush <= 8.1.17 installed then you can use this parameter to call `drush archive-dump` internally.

Example:

```
drupal-client cda:restore /tmp/backup.tar /var/www/html/web-new --overwrite --use-drush=false -vvv
```