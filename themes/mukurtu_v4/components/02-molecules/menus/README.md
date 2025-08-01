# Mukurtu V4 Menu System

## Overview

The Mukurtu V4 menu system is based on [Drupal's Olivero theme navigation](https://www.smashingmagazine.com/2022/09/accessibility-usability-drupal-primary-navigation/), providing a comprehensive, accessible, and responsive navigation solution. This implementation incorporates modern CSS practices and integrates seamlessly with the Mukurtu V4 theme variables while maintaining all the accessibility and usability features that make Olivero's navigation system exceptional.

## Key Features

### Accessibility
- **WCAG 2.1 AA Compliant**: Meets and exceeds accessibility standards
- **Screen Reader Optimized**: Comprehensive ARIA labeling and semantic markup
- **Keyboard Navigation**: Full keyboard support with focus management
- **Forced Colors Support**: Works perfectly with Windows High Contrast mode
- **Focus Trap**: Prevents focus from escaping mobile navigation overlay

### Responsive Design
- **Automatic Responsive Switching**: Switches between mobile and desktop navigation based on content overflow. This is achieved by comparing the height of a menu item with the height of the menu container, take this in account when refining the styling of the menu, if you affect the height of the menu container, it will switch to mobile.
- **Touch-Friendly**: Optimized for touch devices with appropriate touch targets
- **Viewport Overflow Prevention**: Ensures submenus never overflow the viewport

### User Experience
- **Multi-Modal Interaction**: Supports hover, click, and touch interactions on desktop
- **Smooth Animations**: CSS transitions for menu opening/closing and submenu expansion
- **Visual Feedback**: Clear visual indicators for interactive elements
- **Smart Focus Management**: Returns focus to appropriate elements when menus close

## File Structure

### SCSS Components

#### `_menu.scss`
**Purpose**: Main orchestrator file that imports all menu components and provides foundational styling.

#### `_nav-primary.scss`
**Purpose**: Core primary navigation styles optimized for mobile-first approach.

#### `_nav-primary-wide.scss`
**Purpose**: Desktop-specific navigation enhancements and positioning.

#### `_nav-button-mobile.scss`
**Purpose**: Mobile navigation toggle button (hamburger menu) styling.

#### `_nav-primary-button.scss`
**Purpose**: Submenu toggle buttons for expanding second-level navigation.

#### `_header-navigation.scss`
**Purpose**: Navigation container, overlay, and positioning styles.

### JavaScript Components
Javascript files are largely unaltered from the Olivero version, this is on purpose so they can be updated when
there is a new version of them released with Drupal core or when a patch is released for fixing any bugs.
You can find these files originally at `web/core/themes/olivero/js`
#### `navigation.js`
**Purpose**: Main mobile navigation controller managing open/close states and interactions.

**Key Functionality**:
- Toggle navigation open/close states
- Focus trap implementation for mobile navigation
- Keyboard event handling (Escape key)
- Overlay click/touch handling for closing navigation
- Window resize handling for responsive switching
- Anchor link detection for automatic menu closing

#### `second-level-navigation.js`
**Purpose**: Comprehensive submenu functionality for multi-level navigation.

**Key Functionality**:
- Submenu toggle with hover, click, and keyboard support
- Touch event detection to prevent conflicting interactions
- Desktop hover timing management
- Blur event handling for focus-based closing
- ARIA attribute management for expanded states
- Global submenu closing functionality

#### `navigation-utils.js`
**Purpose**: Utility functions and desktop navigation behavior management.

**Key Functionality**:
- Desktop navigation detection based on mobile button visibility
- Sticky header state management with localStorage persistence (this is not implemented in this version of the menu)
- Intersection Observer implementation for scroll-based header behavior
- Toolbar integration and root margin calculations
- Focus management for header elements

#### `nav-resize.js`
**Purpose**: Automatic responsive navigation switching based on content overflow.

**Key Functionality**:
- Resize Observer implementation to detect navigation wrapping
- Automatic mobile/desktop navigation switching
- Media query-based transition management
- Edge case handling for persistent wrapping scenarios

### Accessibility Features

1. **ARIA Implementation**:
   - `aria-labelledby` for navigation region identification
   - `aria-controls` linking buttons to their controlled submenus
   - `aria-expanded` for submenu state communication
   - `aria-hidden` for decorative elements

2. **Keyboard Navigation**:
   - Tab order management with focus trap
   - Escape key handling for menu closing
   - Focus return to parent elements when appropriate
   - Support for screen reader navigation patterns

3. **Screen Reader Support**:
   - Visually hidden text for context ("sub-navigation")
   - Semantic HTML structure with proper heading hierarchy
   - Descriptive button labels and navigation regions

### Responsive Behavior

1. **Automatic Switching**:
   - Uses ResizeObserver to detect when navigation wraps
   - Switches to mobile navigation when content overflows
   - Remembers breakpoint for switching back to desktop view

2. **Mobile Navigation**:
   - Slide-out panel with overlay
   - Touch-optimized interactions
   - Scroll locking when menu is open

3. **Desktop Navigation**:
   - Hover and click interactions
   - Dropdown positioning with viewport overflow prevention
   - Smooth transitions and animations


## Credits

This implementation is based on the exceptional work done by the Drupal Olivero theme team, particularly the navigation system detailed in Mike Herchel's comprehensive article on [Smashing Magazine](https://www.smashingmagazine.com/2022/09/accessibility-usability-drupal-primary-navigation/). The Mukurtu V4 adaptation maintains all the accessibility and usability benefits while integrating with the Mukurtu design system and requirements.
