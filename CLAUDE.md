# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This repository contains two WordPress plugins:

1. **Legacy Image Uploader** (`image_uploader.php`) - Simple image upload tool for WordPress admin
2. **Wayn Headless Images** (`wayn-headless-images/`) - Advanced headless-friendly image delivery system with REST APIs, thumbnail generation, and WebP support

## Current Architecture

### Wayn Headless Images Plugin (Primary)
Modern headless-friendly plugin with the following structure:

```
wayn-headless-images/
├─ wayn-headless-images.php    # Bootstrap file
├─ inc/
│  ├─ Settings.php             # Admin settings interface
│  ├─ Files.php                # File operations and path safety
│  ├─ Thumbs.php               # Thumbnail/WebP generation
│  ├─ Rest.php                 # REST API endpoints + CORS
│  └─ Shortcode.php            # [wayn_gallery] shortcode
└─ tools/
   └─ Regenerate.php           # Admin tool for thumbnail regeneration
```

### Legacy Plugin (Deprecated)
- **Main Plugin File**: `image_uploader.php` - Simple upload interface
- **Template**: `templates/upload_form.php` - Legacy upload form
- **Assets**: `assets/style.css` - Basic styling

## Key Features

### Wayn Headless Images
- **Headless Architecture**: Serves images from custom directories (NAS/CDN) via REST API
- **REST Endpoints**: 
  - `/wp-json/wayn-img/v1/folders` - List available folders
  - `/wp-json/wayn-img/v1/images` - List images with metadata, pagination, sorting
- **Thumbnail System**: Generates multiple sizes (480px, 960px, 1440px) with WebP variants
- **CORS Support**: Configurable CORS origins for frontend integration
- **Path Security**: Prevents directory traversal attacks
- **Responsive Images**: Auto-generated srcset attributes for optimal loading

## Plugin Configuration

### Wayn Headless Images Setup
1. Activate plugin in WordPress admin
2. Go to **Settings → Wayn Images** and configure:
   - **Base Directory**: Server path to image storage (e.g., `/mnt/nas/photos`)
   - **Public Base URL**: CDN/public URL for images (e.g., `https://cdn.waynspace.com/photos`)
   - **Thumbnail Sizes**: Comma-separated pixel widths (default: `480,960,1440`)
   - **JPEG Quality**: Compression level 10-95 (default: `80`)
   - **CORS Origins**: Allowed frontend domains for API access
3. Use **Tools → Regenerate Thumbnails** for existing image folders

### API Usage Examples

**List Folders:**
```
GET /wp-json/wayn-img/v1/folders
```

**List Images:**
```
GET /wp-json/wayn-img/v1/images?dir=street/taipei&page=1&per_page=50&sort=date&order=desc
```

**Shortcode:**
```
[wayn_gallery dir="street/taipei" cols="4"]
```

## Development Notes

- **Path Security**: `whi_safe_path()` prevents directory traversal
- **Image Processing**: Uses PHP GD library for thumbnail generation
- **Performance**: Generates both JPEG and WebP variants for optimal delivery
- **Frontend Integration**: Provides srcset for responsive images
- **No Build Process**: Pure PHP plugin, no compilation required