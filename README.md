# RRZE Autoshare

## Wordpress-Plugin

Dieses Plugin teilt automatisch den Beitragstitel, einen Teil des Textauszugs, das Beitragsbild (falls verfügbar) und einen Link zum Beitrag auf Bluesky, Mastodon und X (Twitter).

### Einstellungsmenü

Einstellungen › Autoshare

### Hooks

#### rrze_autoshare_{$service}_publish_post_directly

```php
add_filter('rrze_autoshare_{$service}_publish_post_directly', int $post_id)
```

Direkt einen Beitrag auf einem Dienst veröffentlichen.

Beschreibung
Der dynamische Teil des Hook-Namens, $service, bezieht sich auf den Dienstnamen.

Mögliche Hook-Namen sind:

```text
rrze_autoshare_bluesky_publish_post_directly
rrze_autoshare_mastodon_publish_post_directly
rrze_autoshare_x_publish_post_directly
```

Parameter

```php
int $post_id
```

Post ID.

#### rrze_autoshare_{$service}_title

```php
add_filter('rrze_autoshare_{$service}_title', string $title, int $post_id)
```

Den Titel eines Beitrags vor dem Teilen filtern.

Beschreibung
Der dynamische Teil des Hook-Namens, $service, bezieht sich auf den Dienstnamen.

Mögliche Hook-Namen sind:

```text
rrze_autoshare_bluesky_title
rrze_autoshare_mastodon_title
rrze_autoshare_x_title
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
add_filter('rrze_autoshare_{$service}_excerpt', string $excerpt, int $post_id)
```

Den Textauszug eines Beitrags vor dem Teilen filtern.

Beschreibung
Der dynamische Teil des Hook-Namens, $service, bezieht sich auf den Dienstnamen.

Mögliche Hook-Namen sind:

```text
rrze_autoshare_bluesky_excerpt
rrze_autoshare_mastodon_excerpt
rrze_autoshare_x_excerpt
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
add_filter('rrze_autoshare_{$service}_hashtags', array $hashtags, int $post_id)
```

Die Hashtags eines Beitrags vor dem Teilen filtern.

Beschreibung
Der dynamische Teil des Hook-Namens, $service, bezieht sich auf den Dienstnamen.

Mögliche Hook-Namen sind:

```text
rrze_autoshare_bluesky_hashtags
rrze_autoshare_mastodon_hashtags
rrze_autoshare_x_hashtags
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
