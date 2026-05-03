import "@testing-library/jest-dom";

declare global {
  // Requis par React 19 pour synchroniser les updates d'état dans les tests
  // https://react.dev/blog/2024/04/25/react-19-upgrade-guide#improvements-to-suspense
  var IS_REACT_ACT_ENVIRONMENT: boolean;
}

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
