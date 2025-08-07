# Version Control Guide - Try-On Tool Plugin

## Version Numbering System

### Semantic Versioning (SemVer)
We follow [Semantic Versioning](https://semver.org/) format: `MAJOR.MINOR.PATCH`

- **MAJOR** (1.x.x): Breaking changes, major new features
- **MINOR** (1.1.x): New features, backward compatible
- **PATCH** (1.1.1): Bug fixes, backward compatible

### Current Version: 1.1.0
- **Previous:** 1.0.0
- **Next Planned:** 1.2.0 (feature release)

## Git Workflow

### Branch Strategy
- `main` - Production-ready code
- `develop` - Development branch (optional)
- `release/v1.x` - Release preparation branches
- `feature/feature-name` - Feature development branches
- `hotfix/v1.x.x` - Critical bug fixes

### Release Process

#### 1. Pre-Release Preparation
```bash
# Ensure you're on main branch
git checkout main
git pull origin main

# Create release branch
git checkout -b release/v1.1

# Make version updates
# - Update version in woo-fashnai-preview.php
# - Update CHANGELOG.md
# - Update RELEASE_CHECKLIST.md

# Commit changes
git add .
git commit -m "Prepare for v1.1 release - Fix GPL headers display issue"
```

#### 2. Testing & Validation
- [ ] Test plugin activation/deactivation
- [ ] Test admin settings page functionality
- [ ] Test frontend templates
- [ ] Verify all syntax errors are fixed
- [ ] Test WooCommerce integration

#### 3. Create Release Tag
```bash
# Create annotated tag
git tag -a v1.1.0 -m "Release version 1.1.0 - Bug fix release"

# Push tag to remote
git push origin v1.1.0
```

#### 4. Merge to Main
```bash
# Switch to main branch
git checkout main

# Merge release branch
git merge release/v1.1

# Push to main
git push origin main

# Delete release branch (optional)
git branch -d release/v1.1
git push origin --delete release/v1.1
```

#### 5. Create GitHub Release
1. Go to GitHub repository
2. Click "Releases" → "Create a new release"
3. Select the `v1.1.0` tag
4. Add release title: "Try-On Tool v1.1.0 - Bug Fix Release"
5. Add release notes from CHANGELOG.md
6. Upload the zip file: `try-on_tool_plugin_v1.1.0.zip`

## Version Update Checklist

### Files to Update for Each Release

#### 1. Main Plugin File (`woo-fashnai-preview.php`)
- [ ] Update version in plugin header: `Version: 1.1.0`
- [ ] Update constant: `define('WOO_FASHNAI_PREVIEW_VERSION', '1.1.0');`
- [ ] Update modification date comment

#### 2. Documentation Files
- [ ] Update `CHANGELOG.md` with new version
- [ ] Update `RELEASE_CHECKLIST.md` version number
- [ ] Update `README.md` version references
- [ ] Update `VERSION_CONTROL.md` current version

#### 3. Template Files
- [ ] Check for version-specific changes in templates
- [ ] Update any hardcoded version references

## Release Types

### Bug Fix Release (PATCH)
- **Example:** 1.1.0 → 1.1.1
- **Changes:** Bug fixes, security patches
- **Process:** Quick release cycle

### Feature Release (MINOR)
- **Example:** 1.1.0 → 1.2.0
- **Changes:** New features, improvements
- **Process:** Full testing cycle

### Major Release (MAJOR)
- **Example:** 1.1.0 → 2.0.0
- **Changes:** Breaking changes, major rewrites
- **Process:** Extended testing, migration guide

## Git Commands Reference

### Basic Workflow
```bash
# Check status
git status

# Add all changes
git add .

# Commit with descriptive message
git commit -m "Type: Brief description of changes"

# Push to remote
git push origin main
```

### Tagging
```bash
# Create annotated tag
git tag -a v1.1.0 -m "Release version 1.1.0"

# List tags
git tag -l

# Push specific tag
git push origin v1.1.0

# Push all tags
git push origin --tags
```

### Branch Management
```bash
# Create new branch
git checkout -b feature/new-feature

# Switch branches
git checkout main

# Delete local branch
git branch -d feature/new-feature

# Delete remote branch
git push origin --delete feature/new-feature
```

## Version History

| Version | Date | Type | Description |
|---------|------|------|-------------|
| 1.1.0 | 2025-08-07 | Bug Fix | Fix GPL headers display, template syntax |
| 1.0.0 | 2025-08-01 | Initial | Initial release |

## Next Release Planning

### Version 1.2.0 (Planned)
- **Target Date:** TBD
- **Type:** Feature Release
- **Planned Features:**
  - Enhanced user interface
  - Additional customization options
  - Performance improvements

### Version 1.1.1 (Hotfix - if needed)
- **Type:** Bug Fix
- **Trigger:** Critical issues discovered in 1.1.0

## Best Practices

1. **Always test before tagging** - Ensure all functionality works
2. **Use descriptive commit messages** - Clear, concise descriptions
3. **Create release notes** - Document all changes for users
4. **Tag releases** - Use semantic versioning tags
5. **Backup before releases** - Keep copies of release files
6. **Update documentation** - Keep all docs current with version

## Emergency Hotfix Process

For critical bugs in production:

```bash
# Create hotfix branch from main
git checkout main
git checkout -b hotfix/v1.1.1

# Make minimal fix
# Update version to 1.1.1
# Update CHANGELOG.md

# Commit and tag
git add .
git commit -m "Hotfix: Critical bug fix"
git tag -a v1.1.1 -m "Hotfix release v1.1.1"

# Push and merge
git push origin hotfix/v1.1.1
git push origin v1.1.1
git checkout main
git merge hotfix/v1.1.1
git push origin main
``` 