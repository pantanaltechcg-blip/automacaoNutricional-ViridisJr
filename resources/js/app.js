const page = document.body?.dataset?.page;

const pages = {
    recipe: () => import('./rotulonutri/pages/recipe.js'),
    table: () => import('./rotulonutri/pages/table.js'),
    label: () => import('./rotulonutri/pages/label.js'),
    ingredient: () => import('./rotulonutri/pages/ingredient.js'),
    'ingredient-edit': () => import('./rotulonutri/pages/ingredient-edit.js'),
    calculation: () => import('./rotulonutri/pages/calculation.js'),
};

if (page && pages[page]) {
    pages[page]().then((mod) => {
        if (typeof mod.init === 'function') {
            mod.init();
        }
    });
}
