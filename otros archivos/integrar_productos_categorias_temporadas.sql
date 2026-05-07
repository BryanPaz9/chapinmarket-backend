--------------------------------------------------------
-- 2. Indices recomendados para filtros y joins
-- Si alguno ya existe, Oracle mostrara error ORA-00955 y puedes omitirlo.
--------------------------------------------------------

CREATE INDEX IDX_PC_CATEGORIA
ON PRODUCTO_CATEGORIAS (CATEGORIA_ID);

CREATE INDEX IDX_PTS_PRODUCTO
ON PRODUCTO_TEMPORADAS (PRODUCTO_ID);

CREATE INDEX IDX_PTS_TEMPORADA
ON PRODUCTO_TEMPORADAS (TEMPORADA_ID);

--------------------------------------------------------
-- 3. Alinear SEQ_TEMPORADA con los datos actuales
--------------------------------------------------------

DECLARE
    v_max_id NUMBER;
    v_next   NUMBER;
BEGIN
    SELECT NVL(MAX(ID), 0) + 1 INTO v_max_id FROM TEMPORADAS;
    SELECT SEQ_TEMPORADA.NEXTVAL INTO v_next FROM DUAL;

    IF v_next < v_max_id THEN
        EXECUTE IMMEDIATE 'ALTER SEQUENCE SEQ_TEMPORADA INCREMENT BY ' || (v_max_id - v_next);
        SELECT SEQ_TEMPORADA.NEXTVAL INTO v_next FROM DUAL;
        EXECUTE IMMEDIATE 'ALTER SEQUENCE SEQ_TEMPORADA INCREMENT BY 1';
    END IF;
END;
/

COMMIT;
