describe('login user', () => {
    it('should login user successfully', () => {
        cy.visit('/login'); // Visit the login page
    
        // Fill in the login form
        cy.get('#userEmail').type('chamath.rmc@gmail.com');
        cy.get('#userPassword').type('7710578@Cha');
        cy.get('#showPassword').click();
        cy.wait(1500);
        cy.get('#showPassword').click();
    
        // Submit the form
        cy.get('#login').click();
        cy.wait(3000);
        cy.get('.swal2-confirm').click();
    });
})