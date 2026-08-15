# Coding Style
- Properly create classes 
- Use SOLID principle
- Dont split files unecesseraly
- Gather commonly used functions as helpers
- Do not write comments unless necessary
- Instead of depending on library for everything try to minize unless necessary

# Commit Style
- Short and concise message
- Instead commit all in one go, split commits to sensible splits

# UI Style
- User friendly, UI-UX Optimized
- If using node, make sure every thirdparty is installed on sandbox to avoid supply chain
- Do not use library for everything, but also do not need to reinvent the cycle

# Docker guidelines
- Change dockerfile and compose.yml as required
- Do not add comment unless necessary
- For prod make sure node is excluded as it is needed only for building
- use volumes as sensible

# Other
- Add .gitignore and .dockerignore as required and update as required
- Generate and update README.md 
- Keys like, OPENAI, CLAUDE, or any secretive token should be read directly but can be used by code you write, but never read them
- do not add co-authored in commit message