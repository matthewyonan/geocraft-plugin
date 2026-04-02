# GeoCraft WordPress Plugin

Publish AI-optimized content from [GeoCraft](https://geocraft.ai) directly to your WordPress site.

## What is GeoCraft?

GeoCraft is an AI content platform that creates articles optimized for Generative Engine Optimization (GEO) — content designed to rank in traditional search engines and get cited by AI-powered answer engines.

## What This Plugin Does

This plugin connects your WordPress site to GeoCraft so you can:

- **Publish content in one click** — Articles, formatting, and images appear on your site without copy-pasting
- **Sync SEO metadata** — Meta titles, descriptions, and schema markup are set automatically (works with Yoast, RankMath, AIOSEO)
- **Track performance** — Pageview and engagement analytics flow back to your GeoCraft dashboard
- **Schedule posts** — Queue content for future publication dates
- **Map categories & tags** — Content is automatically organized into your WordPress taxonomies

## Requirements

- WordPress 6.0+
- PHP 8.0+
- An active [GeoCraft](https://geocraft.ai) subscription

## Installation

### From WordPress Plugin Directory

1. Go to **Plugins → Add New** in your WordPress admin
2. Search for "GeoCraft"
3. Click **Install Now**, then **Activate**

### Manual Installation

1. Download the latest release from the [Releases page](https://github.com/matthewyonan/geocraft-plugin/releases)
2. Upload the ZIP file via **Plugins → Add New → Upload Plugin**
3. Activate the plugin

## Setup

1. Navigate to **Settings → GeoCraft**
2. Enter your API key (find it in your [GeoCraft dashboard](https://app.geocraft.ai) under **Integrations**)
3. Configure your default post status (draft or published) and category mappings
4. Done — content is ready to flow

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Check coding standards
composer lint
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for development guidelines.

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) for details.
