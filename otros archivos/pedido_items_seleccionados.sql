ALTER SESSION SET CURRENT_SCHEMA = CHAPINMARKET;

--------------------------------------------------------
-- PEDIDOS PARCIALES DESDE CARRITO
-- Solo los items con SELECCIONADO = 1 pasan al pedido.
-- Los items con SELECCIONADO = 0 quedan en el carrito.
--------------------------------------------------------

--------------------------------------------------------
-- 1. Asegurar columna de seleccion en carrito
--------------------------------------------------------

ALTER TABLE CARRITO_ITEMS MODIFY (
    SELECCIONADO DEFAULT 1 NOT NULL
);

UPDATE CARRITO_ITEMS
SET SELECCIONADO = 1
WHERE SELECCIONADO IS NULL;

COMMIT;

--------------------------------------------------------
-- 2. Reemplazar SP de procesamiento de pago
-- Soporta usuario registrado e invitado.
--------------------------------------------------------

CREATE OR REPLACE PROCEDURE PROCESAR_PAGO_CARRITO (
    p_usuario_id          IN NUMBER DEFAULT NULL,
    p_carrito_id          IN NUMBER,
    p_direccion_id        IN NUMBER DEFAULT NULL,
    p_titular             IN VARCHAR2,
    p_numero_tarjeta      IN VARCHAR2,
    p_vencimiento         IN VARCHAR2,
    p_pedido_id           OUT NUMBER,

    -- Campos para compra invitada
    p_direccion_envio     IN VARCHAR2 DEFAULT NULL,
    p_nombre_contacto     IN VARCHAR2 DEFAULT NULL,
    p_correo_contacto     IN VARCHAR2 DEFAULT NULL,
    p_telefono_contacto   IN VARCHAR2 DEFAULT NULL,
    p_session_id          IN VARCHAR2 DEFAULT NULL,
    p_envio               IN NUMBER DEFAULT 25
)
AS
    v_total              NUMBER(10,2) := 0;
    v_direccion_envio    VARCHAR2(1000);
    v_nombre_contacto    VARCHAR2(150);
    v_correo_contacto    VARCHAR2(150);
    v_telefono_contacto  VARCHAR2(30);
    v_count              NUMBER := 0;
BEGIN
    --------------------------------------------------------
    -- Validar carrito segun flujo
    --------------------------------------------------------

    IF p_usuario_id IS NOT NULL THEN
        SELECT COUNT(*)
        INTO v_count
        FROM CARRITOS
        WHERE ID = p_carrito_id
          AND USUARIO_ID = p_usuario_id;
    ELSE
        SELECT COUNT(*)
        INTO v_count
        FROM CARRITOS
        WHERE ID = p_carrito_id
          AND USUARIO_ID IS NULL
          AND SESSION_ID = p_session_id;
    END IF;

    IF v_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20001, 'Carrito no valido para este cliente.');
    END IF;

    --------------------------------------------------------
    -- Validar que existan items seleccionados
    --------------------------------------------------------

    SELECT COUNT(*)
    INTO v_count
    FROM CARRITO_ITEMS
    WHERE CARRITO_ID = p_carrito_id
      AND SELECCIONADO = 1;

    IF v_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20002, 'No hay productos seleccionados para pagar.');
    END IF;

    --------------------------------------------------------
    -- Validar stock solo de items seleccionados
    --------------------------------------------------------

    FOR item IN (
        SELECT ci.PRODUCTO_ID, ci.CANTIDAD, p.STOCK, p.NOMBRE
        FROM CARRITO_ITEMS ci
        JOIN PRODUCTOS p ON p.ID = ci.PRODUCTO_ID
        WHERE ci.CARRITO_ID = p_carrito_id
          AND ci.SELECCIONADO = 1
    ) LOOP
        IF item.STOCK < item.CANTIDAD THEN
            RAISE_APPLICATION_ERROR(
                -20003,
                'Stock insuficiente para el producto: ' || item.NOMBRE
            );
        END IF;
    END LOOP;

    --------------------------------------------------------
    -- Calcular total solo de items seleccionados
    --------------------------------------------------------

    SELECT NVL(SUM(ci.CANTIDAD * p.PRECIO), 0) + NVL(p_envio, 0)
    INTO v_total
    FROM CARRITO_ITEMS ci
    JOIN PRODUCTOS p ON p.ID = ci.PRODUCTO_ID
    WHERE ci.CARRITO_ID = p_carrito_id
      AND ci.SELECCIONADO = 1;

    --------------------------------------------------------
    -- Resolver direccion y contacto
    --------------------------------------------------------

    IF p_usuario_id IS NOT NULL THEN
        IF p_direccion_id IS NULL THEN
            RAISE_APPLICATION_ERROR(-20004, 'Debe seleccionar una direccion de envio.');
        END IF;

        SELECT
            LINEA1 ||
            CASE WHEN LINEA2 IS NOT NULL THEN ', ' || LINEA2 ELSE '' END ||
            ', ' || CIUDAD ||
            ', ' || DEPARTAMENTO ||
            CASE WHEN CODIGO_POSTAL IS NOT NULL THEN ', CP ' || CODIGO_POSTAL ELSE '' END
        INTO v_direccion_envio
        FROM DIRECCIONES
        WHERE ID = p_direccion_id
          AND USUARIO_ID = p_usuario_id;

        SELECT NOMBRE, CORREO, TELEFONO
        INTO v_nombre_contacto, v_correo_contacto, v_telefono_contacto
        FROM USUARIOS
        WHERE ID = p_usuario_id;
    ELSE
        IF p_direccion_envio IS NULL OR p_nombre_contacto IS NULL OR p_correo_contacto IS NULL THEN
            RAISE_APPLICATION_ERROR(-20005, 'Faltan datos de contacto para compra invitada.');
        END IF;

        v_direccion_envio := p_direccion_envio;
        v_nombre_contacto := p_nombre_contacto;
        v_correo_contacto := p_correo_contacto;
        v_telefono_contacto := p_telefono_contacto;
    END IF;

    --------------------------------------------------------
    -- Crear pedido
    --------------------------------------------------------

    INSERT INTO PEDIDOS (
        USUARIO_ID,
        FECHA,
        TOTAL,
        ESTADO,
        DIRECCION_ENVIO,
        NOMBRE_CONTACTO,
        CORREO_CONTACTO,
        TELEFONO_CONTACTO,
        SESSION_ID
    )
    VALUES (
        p_usuario_id,
        SYSDATE,
        v_total,
        'pendiente',
        v_direccion_envio,
        v_nombre_contacto,
        v_correo_contacto,
        v_telefono_contacto,
        p_session_id
    )
    RETURNING ID INTO p_pedido_id;

    --------------------------------------------------------
    -- Crear items del pedido solo seleccionados
    --------------------------------------------------------

    INSERT INTO PEDIDO_ITEMS (
        PEDIDO_ID,
        PRODUCTO_ID,
        CANTIDAD,
        PRECIO_UNITARIO
    )
    SELECT
        p_pedido_id,
        ci.PRODUCTO_ID,
        ci.CANTIDAD,
        p.PRECIO
    FROM CARRITO_ITEMS ci
    JOIN PRODUCTOS p ON p.ID = ci.PRODUCTO_ID
    WHERE ci.CARRITO_ID = p_carrito_id
      AND ci.SELECCIONADO = 1;

    --------------------------------------------------------
    -- Descontar stock solo seleccionados
    --------------------------------------------------------

    FOR item IN (
        SELECT PRODUCTO_ID, CANTIDAD
        FROM CARRITO_ITEMS
        WHERE CARRITO_ID = p_carrito_id
          AND SELECCIONADO = 1
    ) LOOP
        UPDATE PRODUCTOS
        SET STOCK = STOCK - item.CANTIDAD
        WHERE ID = item.PRODUCTO_ID;
    END LOOP;

    --------------------------------------------------------
    -- Limpiar solo seleccionados del carrito.
    -- Los no seleccionados quedan para la misma sesion.
    --------------------------------------------------------

    DELETE FROM CARRITO_ITEMS
    WHERE CARRITO_ID = p_carrito_id
      AND SELECCIONADO = 1;

EXCEPTION
    WHEN NO_DATA_FOUND THEN
        RAISE_APPLICATION_ERROR(-20006, 'No se encontraron datos necesarios para procesar el pago.');
    WHEN OTHERS THEN
        RAISE;
END;
/

COMMIT;
