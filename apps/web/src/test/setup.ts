import "@testing-library/jest-dom";

// React 19 exige que l'environnement de test soit déclaré explicitement
// pour que les mises à jour d'état soient correctement wrappées dans act()
globalThis.IS_REACT_ACT_ENVIRONMENT = true;
