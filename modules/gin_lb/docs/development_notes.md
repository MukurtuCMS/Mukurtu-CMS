# Development notes


## Assets compilation


### Installation

```shell
curl https://raw.githubusercontent.com/creationix/nvm/master/install.sh | bash
nvm install 14
```

### Regular script execution

Gin Layout Builder comes with a webpack configuration to build CSS and JS.

To use it run:
```shell
nvm use 14
yarn install
yarn dev
```

or

```shell
nvm use 14
yarn install
yarn build
```
