import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        include: ['tests/js/**/*.test.js'],
        // No DOM needed: the logger is executed in a vm sandbox with stubs.
        environment: 'node',
    },
});
