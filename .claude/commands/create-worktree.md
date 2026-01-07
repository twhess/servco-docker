Follow these steps to create a git worktree.

1. Get the current projects's folder name.

2. Create a folder adjacent to he current project's folder and name it {current project folder name}-worktrees. For example, if the current project folder is namedmyapp, create a folder called myapp-worktrees. Boh myapp and the myapp-worktrees should be in the parent folder.

3. Create a git worktree and branch named $ARGUMENTS from the main project folder and save it inside the {current project folder name}-worktree folder that was created.

4. cd into the new $ARGUMENTS worktree folder.