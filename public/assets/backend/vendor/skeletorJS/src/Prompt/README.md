# Prompt

## Usage
```javascript
const prompt = new Prompt({
    message: 'Are you sure?',
    description: 'This action cannot be undone.',
    choices: [
        { value:0, text: 'No'},
        { value:1, text: 'Yes'}
    ]
});

prompt.prompt().then((value) => {
    if(value === 1) {
        console.log('User confirmed');
    } else {
        console.log('User denied');
    }
});
```