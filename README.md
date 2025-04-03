# IP2∞Social.network

A custom-built forum with a focus on IP2 streamers, video sharing, and community interaction.

## Features

- User registration and login system
- Post creation with text, links, and videos
- Video upload functionality
- Emote support with #/EmoteName syntax
- Live streamers page with YouTube and Kick integration
- Post flair/tags system
- Sorting options (hot, top, new)
- Responsive design with dark theme
- Mobile-friendly interface

## Technical Details

- Built with PHP and SQLite
- No framework used - clean, minimal code
- File-based database for easy deployment
- Custom-built templating system
- Responsive design with CSS

## Directory Structure

- `public/` - All publicly accessible files
  - `index.php` - Main page with post timeline
  - `login.php` & `register.php` - Authentication
  - `upload.php` - Video upload functionality
  - `live.php` - Streaming integration
  - `emotes.php` - Emote management
  - `create_post.php` - Post creation
  - `assets/` - Static assets (images, etc.)
  - `includes/` - PHP include files
    - `functions.php` - Common functions
    - `emotes.php` - Emote parsing functions
  - `uploads/` - User-uploaded content
- `storage/` - Database and other persistent storage
  - `sqlite/` - SQLite database files

## Installation

See `DEPLOYMENT.md` for detailed setup instructions.

## Default Login

- Username: admin
- Password: admin123

## Customization

- Banner can be changed by replacing `public/assets/images/banner.png`
- Colors and styling can be modified in `public/styles.css`
- Emotes can be managed through the Emotes page

## External API Integration

- YouTube Data API for live streaming status
- Kick API for streamer information

## License

This software is proprietary and not licensed for redistribution.
