[![Aktuelle Version](https://img.shields.io/github/package-json/v/rrze-webteam/rrze-autoshare/main?label=Version)](https://github.com/RRZE-Webteam/rrze-autoshare) [![Release Version](https://img.shields.io/github/v/release/rrze-webteam/rrze-autoshare?label=Release+Version)](https://github.com/rrze-webteam/rrze-autoshare/releases/) [![GitHub License](https://img.shields.io/github/license/rrze-webteam/rrze-autoshare)](https://github.com/RRZE-Webteam/rrze-autoshare) [![GitHub issues](https://img.shields.io/github/issues/RRZE-Webteam/rrze-autoshare)](https://github.com/RRZE-Webteam/rrze-autoshare/issues)

# RRZE Autoshare

Dieses Plugin teilt automatisch den Beitragstitel, einen Teil des Textauszugs, das Beitragsbild (falls verfügbar) und einen Link zum Beitrag auf Bluesky und Mastodon.


## Contributors

* RRZE-Webteam, https://www.rrze.fau.de

## Copyright

GNU General Public License (GPL) Version 3

## Documentation

Die oeffentliche Dokumentation und Endanwender-Hinweise liegen unter:

* https://www.wp.rrze.fau.de

## Feedback

* Issues und Feedback: https://github.com/RRZE-Webteam/rrze-autoshare/issues
* Kontakt: webmaster@rrze.fau.de



### Einstellungsmenü

Einstellungen › Autoshare

### Hooks

#### rrze_autoshare_supported_post_types

```php
apply_filters('rrze_autoshare_supported_post_types', array $post_types)
```

Filtert die vom Plugin unterstützten Post-Types.

Parameter

```php
array $post_types
```

Post-Types.

#### rrze_autoshare_{$service}_title

```php
apply_filters('rrze_autoshare_{$service}_title', string $title, int $post_id)
```

Den Titel eines Beitrags vor dem Teilen filtern.

Beschreibung
Der dynamische Teil des Hook-Namens, $service, bezieht sich auf den Dienstnamen.

Mögliche Hook-Namen sind:

```text
rrze_autoshare_bluesky_title
rrze_autoshare_mastodon_title
```

Parameter

```php
string $title
```

Post title.

```php
int $post_id
```

Post ID.

#### rrze_autoshare_{$service}_excerpt

```php
apply_filters('rrze_autoshare_{$service}_excerpt', string $excerpt, int $post_id)
```

Den Textauszug eines Beitrags vor dem Teilen filtern.

Beschreibung
Der dynamische Teil des Hook-Namens, $service, bezieht sich auf den Dienstnamen.

Mögliche Hook-Namen sind:

```text
rrze_autoshare_bluesky_excerpt
rrze_autoshare_mastodon_excerpt
```

Parameter

```php
string $excerpt
```

Post excerpt.

```php
int $post_id
```

Post ID.

#### rrze_autoshare_{$service}_hashtags

```php
apply_filters('rrze_autoshare_{$service}_hashtags', array $hashtags, int $post_id)
```

Die Hashtags eines Beitrags vor dem Teilen filtern. Standardmäßig werden nicht-hierarchische Taxonomien verwendet, indem # vorangestellt wird.

Beschreibung
Der dynamische Teil des Hook-Namens, $service, bezieht sich auf den Dienstnamen.

Mögliche Hook-Namen sind:

```text
rrze_autoshare_bluesky_hashtags
rrze_autoshare_mastodon_hashtags
```

Parameter

```php
array $hashtags
```

An array of hashtags e.g., ['#hashtag1', '#hashtag2'].

```php
int $post_id
```

Post ID.
