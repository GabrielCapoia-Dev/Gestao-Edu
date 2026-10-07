import { Column, Entity, JoinColumn, OneToOne, PrimaryColumn, UpdateDateColumn } from 'typeorm';
import { Person } from './person.entity';

// US003/CT02: conta 1:1 usa o UUID da pessoa; identidade Google é o subject, nunca token.
@Entity('user_accounts')
export class UserAccount {
  @PrimaryColumn({ name: 'person_uuid', type: 'char', length: 36 }) personUuid!: string;
  @Column({ name: 'login_email', type: 'varchar', length: 320 }) loginEmail!: string;
  @Column({ type: 'varchar', length: 16 }) status!: 'active' | 'inactive';
  @Column({ name: 'google_subject', type: 'varchar', length: 255, nullable: true, unique: true }) googleSubject!: string | null;
  @Column({ name: 'google_display_name', type: 'varchar', length: 200, nullable: true }) googleDisplayName!: string | null;
  @Column({ name: 'google_photo_url', type: 'varchar', length: 2048, nullable: true }) googlePhotoUrl!: string | null;
  @UpdateDateColumn({ name: 'updated_at', type: 'datetime', precision: 6 }) updatedAt!: Date;
  @OneToOne(() => Person, (person) => person.account, { onDelete: 'RESTRICT' })
  @JoinColumn({ name: 'person_uuid', referencedColumnName: 'uuid' }) person!: Person;
}
