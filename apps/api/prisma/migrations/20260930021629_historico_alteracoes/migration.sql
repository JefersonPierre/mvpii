-- CreateTable
CREATE TABLE "registro_auditoria" (
    "id" SERIAL NOT NULL,
    "usuario_id" INTEGER,
    "entidade" VARCHAR(50) NOT NULL,
    "registro_id" INTEGER NOT NULL,
    "acao" VARCHAR(20) NOT NULL,
    "campo" VARCHAR(50),
    "valor_anterior" TEXT,
    "valor_novo" TEXT,
    "data_hora" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "registro_auditoria_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE INDEX "registro_auditoria_entidade_registro_id_idx" ON "registro_auditoria"("entidade", "registro_id");

-- AddForeignKey
ALTER TABLE "registro_auditoria" ADD CONSTRAINT "registro_auditoria_usuario_id_fkey" FOREIGN KEY ("usuario_id") REFERENCES "usuario"("id") ON DELETE SET NULL ON UPDATE CASCADE;
