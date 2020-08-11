# git-hooks
yet another php git hooks

Inspired by a blog post written by Carlos Buenosvinos

Add this to your composer.json.
Warning: This will replace any existing .git/hooks/pre-commit file and put a
symbolic link to the hook/pre-commit file of this project.
````yaml
    "scripts": {
        "create-git-hooks": [
            "[ $COMPOSER_DEV_MODE -eq 0 ] || (mkdir -p .git/hooks && ln -f -s ../../vendor/enrj/git-hooks/hooks/pre-commit .git/hooks/pre-commit)"
        ]
    },
````
then use the command "composer create-git-hooks"

Create the git_hooks.yml to configure what will be used.
````yaml
git_hooks:
    phpLint: true
    phpCsFixer: true
    phpCs: true
    phpMd: true
    twigCs: true
````

optional parameter to put in git_hooks.yml
````yaml
    ignore_folder:
        - src/Migrations
````
