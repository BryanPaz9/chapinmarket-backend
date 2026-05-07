ALTER SESSION SET CURRENT_SCHEMA = CHAPINMARKET;

--------------------------------------------------------
-- COMPRA INVITADA / ANONIMA
--------------------------------------------------------

--------------------------------------------------------
-- 1. Permitir pedidos sin usuario registrado
--------------------------------------------------------

ALTER TABLE PEDIDOS MODIFY (USUARIO_ID NULL);

--------------------------------------------------------
-- 2. Agregar datos de contacto al pedido
--------------------------------------------------------

ALTER TABLE PEDIDOS ADD (
    NOMBRE_CONTACTO    VARCHAR2(150),
    CORREO_CONTACTO    VARCHAR2(150),
    TELEFONO_CONTACTO  VARCHAR2(30),
    SESSION_ID         VARCHAR2(100)
);

--------------------------------------------------------
-- 3. Ampliar direccion de envio para direccion escrita
-- por usuario invitado
--------------------------------------------------------

ALTER TABLE PEDIDOS MODIFY (
    DIRECCION_ENVIO VARCHAR2(1000)
);

--------------------------------------------------------
-- 4. Indices utiles para pedidos invitados
-- Si alguno ya existe, Oracle mostrara ORA-00955 y puedes omitirlo.
--------------------------------------------------------

CREATE INDEX IDX_PEDIDOS_SESSION
ON PEDIDOS (SESSION_ID);

CREATE INDEX IDX_PEDIDOS_CORREO_CONTACTO
ON PEDIDOS (CORREO_CONTACTO);

COMMIT;
