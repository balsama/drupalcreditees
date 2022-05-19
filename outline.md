Commit
1. Issue Number (int)
2. Creditees (array)
3. Datetime (DATETIME)
4. Hash

Creditee
1. UID (UID)
2. Name (string)
3. Account created (DATETIME)
4. Issue credits (int)

Visualizations:
1. Average age of accounts at first core credit by year of credit
    For accounts that got their first commit in year YYYY, how were thos accounts on average?
2. Core contributors by year of account creation
    Stacked by the year the first credit was given
3. Acquia vs the world in credits by month by issue
    Percent of total commits for which Acquia was credited
4. Acquia vs the world in credits by month by credit
    Percent of total commits credits for which Acquia was credited
5. Total commits per year and total usernames per commit 

Outline:
Commands:
1. Gather Commits (should output commits.json via Repos.php)
2. Gather Creditees (should output creditees.json via Creditees.php)

Classes:
Repos.php
2. Gather all the commits from the repos logs
3. Process those commits
   1. Get the usernames of the creditees
4. Output to commits.json
5. @TODO Only get new commits and update commits.json

Creditees.php
1. Take input from commits.json
2. Find unique creditees from all commits
3. Find out which of those creditees are valid, current D.O users
4. Process valid creditees
5. Output to creditees.json