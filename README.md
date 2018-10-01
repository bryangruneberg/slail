## Slack + Tail = SLAIL

### Installation
You'll need PHP and composer (see getcomposer.org).

`composer global require bryangruneberg/slail`

Make sure your composer global vendor directory is in your path.

### Configuration
Edit/create the file `~/.slail.conf` as follows

```
{ 
  "token": "<LEGACY TOKEN FROM: https://api.slack.com/custom-integrations/legacy-tokens>" 
}
```
Alternatively, you can store your SLACK Token in the environment variable SLACK_TOKEN. In Bash:

```
export SLAIL_TOKEN="YOUR-TOKEN"
```

## License
SLAIL is an open-source software licensed under the MIT license
