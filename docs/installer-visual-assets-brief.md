# Project Bantay Bayan Installer Visual Assets Brief

This brief defines the visual assets needed for the Project Bantay Bayan desktop installer.

## Product Identity

- Product name: `Project Bantay Bayan`
- Technical subtitle: `PBB Node Kit Setup`
- Purpose: desktop installer used by local administrators to install and configure a PBB node kit.
- Tone: civic, reliable, calm, operational, official.

Avoid a playful or consumer-app look. The installer should feel trustworthy for city/municipality use.

## Required Assets

### 1. App Icon

File:

```text
assets/branding/app-icon.ico
```

Format:

- Windows `.ico`
- Transparent background
- Multi-size ICO containing:
  - `16 x 16`
  - `24 x 24`
  - `32 x 32`
  - `48 x 48`
  - `64 x 64`
  - `128 x 128`
  - `256 x 256`

Used in:

- installer window
- installed executable
- taskbar
- desktop shortcut
- Start Menu shortcut

### 2. Installer Sidebar Image

File:

```text
assets/branding/installer-sidebar.bmp
```

Format:

- Windows bitmap `.bmp`
- Size: `164 x 314 px`
- No transparency

Used in:

- left panel of the Windows setup wizard

Design notes:

- Should still look good beside white installer content.
- Avoid small text because it will not be readable.
- Prefer strong brand mark, clean composition, and restrained colors.

### 3. Installer Header Image

File:

```text
assets/branding/installer-header.bmp
```

Format:

- Windows bitmap `.bmp`
- Size: `150 x 57 px`
- No transparency

Used in:

- upper-right area of some Windows setup wizard pages

Design notes:

- Keep it simple.
- Use logo/mark only or logo plus very short text.
- Must remain readable at small size.

## Recommended Source Assets

These are not all required by the installer immediately, but they are useful for future builds, docs, splash screens, and generated variants.

### 4. Master Logo

File:

```text
assets/branding/logo-1024.png
```

Format:

- PNG
- `1024 x 1024 px`
- Transparent background

Used for:

- source for icon generation
- documentation
- future splash screen
- release pages

### 5. Wide Banner

File:

```text
assets/branding/banner.png
```

Format:

- PNG
- Suggested size: `1200 x 400 px`

Used for:

- README
- release notes
- future welcome screen

### 6. Splash / Loading Image

File:

```text
assets/branding/splash.png
```

Format:

- PNG
- Suggested size: `800 x 480 px`

Used for:

- optional future app launch splash screen

## Suggested Folder Structure

```text
assets/
  branding/
    app-icon.ico
    logo-1024.png
    installer-sidebar.bmp
    installer-header.bmp
    banner.png
    splash.png
```

## Color Guidance

Preferred direction:

- official civic palette
- clean contrast
- works on white/light installer background
- should remain recognizable in small icon sizes

Avoid:

- overly dark or low-contrast graphics
- tiny text inside the icon
- overly complex seal-like details that disappear at `16 x 16`
- generic tech gradients that do not communicate civic/public-safety context

## Text Guidance

Use:

- `Project Bantay Bayan`
- optional subtitle: `PBB Node Kit Setup`

Avoid placing long text in the sidebar/header images. The installer itself will render the product name in text.

## Delivery Checklist

- [ ] `app-icon.ico`
- [ ] `installer-sidebar.bmp`
- [ ] `installer-header.bmp`
- [ ] `logo-1024.png`
- [ ] `banner.png`
- [ ] `splash.png`

The first three files are required for the next installer branding pass. The PNG files are recommended source/support assets.
